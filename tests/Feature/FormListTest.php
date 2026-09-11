<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormType;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Antragsübersicht (FormsController::adminList) sortiert, filtert und
 * blättert auf dem Server: Kopfzellen als Links, Suche als GET-Formular,
 * Status als Filter-Links. Standard ist das Datum absteigend.
 */
final class FormListTest extends FeatureTestCase
{
    private FormType $typ;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $this->typ = new FormType();
        $this->typ->name  = 'Urlaub_' . uniqid();
        $this->typ->aktiv = true;
        $this->typ->save();
    }

    private function antrag(string $nr, string $von, int $status, string $time): Form
    {
        $antrag = new Form();
        $antrag->uniqueid      = $nr;
        $antrag->antragstyp_id = $this->typ->id;
        $antrag->name_dn       = $von;
        $antrag->cirs_status   = $status;
        $antrag->time_added    = new \DateTime($time);
        $antrag->save();

        return $antrag;
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function standard_ist_datum_absteigend_und_spalten_lassen_sich_sortieren(): void
    {
        $this->antrag('A0000001', 'Zed Alt', Form::STATUS_IN_PROGRESS, '2026-01-01 10:00:00');
        $this->antrag('A0000002', 'Anna Neu', Form::STATUS_ACCEPTED, '2026-02-01 10:00:00');

        $default = $this->get('/forms/admin/list');
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertMatchesRegularExpression('~aria-sort="descending"><a class="ignis-table__sort is-desc" href="/forms/admin/list\?sort=datum&amp;dir=asc">Datum~', $default->body);
        $this->assertLessThan($this->pos($default->body, 'A0000001'), $this->pos($default->body, 'A0000002'));

        $byName = $this->get('/forms/admin/list', ['query' => ['sort' => 'von', 'dir' => 'asc']]);
        $this->assertLessThan($this->pos($byName->body, 'Zed Alt'), $this->pos($byName->body, 'Anna Neu'));

        $unknown = $this->get('/forms/admin/list', ['query' => ['sort' => 'cirs_text', 'dir' => 'asc']]);
        $this->assertBodyContains('aria-sort="descending"><a class="ignis-table__sort is-desc" href="/forms/admin/list?sort=datum&amp;dir=asc">Datum', $unknown);
    }

    #[Test]
    public function suche_und_statusfilter(): void
    {
        $this->antrag('B0000001', 'Frieda Filter', Form::STATUS_REJECTED, '2026-01-01 10:00:00');
        $this->antrag('B0000002', 'Frieda Filter', Form::STATUS_ACCEPTED, '2026-01-02 10:00:00');
        $this->antrag('B0000003', 'Otto Anders', Form::STATUS_ACCEPTED, '2026-01-03 10:00:00');

        $found = $this->get('/forms/admin/list', ['query' => ['q' => 'Frieda']]);
        $this->assertBodyContains('B0000001', $found);
        $this->assertBodyContains('B0000002', $found);
        $this->assertBodyNotContains('B0000003', $found);
        $this->assertBodyContains('2 von 2 Anträge', $found);

        $accepted = $this->get('/forms/admin/list', ['query' => ['q' => 'Frieda', 'status' => (string) Form::STATUS_ACCEPTED]]);
        $this->assertBodyContains('B0000002', $accepted);
        $this->assertBodyNotContains('B0000001', $accepted);
        $this->assertBodyContains('href="/forms/admin/list?q=Frieda&amp;status=3" class="is-active"', $accepted);

        $byNumber = $this->get('/forms/admin/list', ['query' => ['q' => 'B0000003']]);
        $this->assertBodyContains('Otto Anders', $byNumber);
        $this->assertBodyNotContains('Frieda Filter', $byNumber);
    }

    #[Test]
    public function seite_zwei(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->antrag(sprintf('P%07d', $i), 'Seite Zwei', Form::STATUS_IN_PROGRESS, sprintf('2026-03-%02d 10:00:00', $i));
        }

        $first = $this->get('/forms/admin/list', ['query' => ['q' => 'Seite Zwei']]);
        $this->assertBodyContains('P0000026', $first);
        $this->assertBodyNotContains('P0000001<', $first);
        $this->assertBodyContains('1–25 von 26 Anträge', $first);

        $second = $this->get('/forms/admin/list', ['query' => ['q' => 'Seite Zwei', 'page' => '2']]);
        $this->assertBodyContains('P0000001', $second);
        $this->assertBodyNotContains('P0000026', $second);
        $this->assertBodyContains('aria-current="page">2<', $second);
    }
}
