<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der globale Audit-Log (UserController::auditlog) sortiert, sucht, filtert
 * nach Modul und blättert auf dem Server, 50 Einträge je Seite, neueste
 * zuerst.
 */
final class AuditLogListTest extends FeatureTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['username' => 'audit_admin_' . uniqid()]);
        $this->userId = $user->id;
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function entry(string $module, string $action, string $details, string $timestamp): void
    {
        Capsule::table('intra_audit_log')->insert([
            'user'      => $this->userId,
            'module'    => $module,
            'action'    => $action,
            'details'   => $details,
            'global'    => 1,
            'timestamp' => $timestamp,
        ]);
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function neueste_zuerst_und_sortierung_nach_modul(): void
    {
        $this->entry('zmodul', 'Alter Eintrag', 'audit-sort', '2026-01-01 10:00:00');
        $this->entry('amodul', 'Neuer Eintrag', 'audit-sort', '2026-02-01 10:00:00');

        $default = $this->get('/users/audit-log', ['query' => ['q' => 'audit-sort']]);
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertBodyContains('aria-sort="descending"><a class="ignis-table__sort is-desc" href="/users/audit-log?q=audit-sort&amp;sort=zeit&amp;dir=asc">Zeitstempel', $default);
        $this->assertLessThan($this->pos($default->body, 'Alter Eintrag'), $this->pos($default->body, 'Neuer Eintrag'));

        $byModule = $this->get('/users/audit-log', ['query' => ['q' => 'audit-sort', 'sort' => 'modul', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($byModule->body, 'Neuer Eintrag'), $this->pos($byModule->body, 'Alter Eintrag'));

        $unknown = $this->get('/users/audit-log', ['query' => ['q' => 'audit-sort', 'sort' => 'details']]);
        $this->assertBodyContains('is-desc" href="/users/audit-log?q=audit-sort&amp;sort=zeit&amp;dir=asc">Zeitstempel', $unknown);
    }

    #[Test]
    public function suche_und_modulfilter(): void
    {
        $this->entry('filtermodul', 'Rolle geändert', 'Treffer eins', '2026-01-01 10:00:00');
        $this->entry('anderesmodul', 'Rolle geändert', 'Treffer zwei', '2026-01-02 10:00:00');
        $this->entry('filtermodul', 'Passwort gesetzt', 'kein Treffer', '2026-01-03 10:00:00');

        $found = $this->get('/users/audit-log', ['query' => ['q' => 'Rolle geändert']]);
        $this->assertBodyContains('Treffer eins', $found);
        $this->assertBodyContains('Treffer zwei', $found);
        $this->assertBodyNotContains('kein Treffer', $found);

        $filtered = $this->get('/users/audit-log', ['query' => ['q' => 'Rolle geändert', 'modul' => 'filtermodul']]);
        $this->assertBodyContains('Treffer eins', $filtered);
        $this->assertBodyNotContains('Treffer zwei', $filtered);
        $this->assertBodyContains('value="filtermodul" selected', $filtered);
    }

    #[Test]
    public function seite_zwei(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $this->entry('seiten', sprintf('Eintrag %02d', $i), 'audit-page', sprintf('2026-03-01 %02d:%02d:00', intdiv($i, 60), $i % 60));
        }

        $first = $this->get('/users/audit-log', ['query' => ['q' => 'audit-page']]);
        $this->assertBodyContains('Eintrag 51', $first);
        $this->assertBodyContains('Eintrag 02', $first);
        $this->assertBodyNotContains('Eintrag 01<', $first);
        $this->assertBodyContains('1–50 von 51 Einträge', $first);

        $second = $this->get('/users/audit-log', ['query' => ['q' => 'audit-page', 'page' => '2']]);
        $this->assertBodyContains('Eintrag 01', $second);
        $this->assertBodyNotContains('Eintrag 02', $second);
    }
}
