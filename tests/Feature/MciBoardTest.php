<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das MANV-Board (plugins/manv-board, MciController::board) sortiert die
 * Patiententabelle auf dem Server (App\Support\ListQuery): Standard ist
 * Sichtungskategorie, dann Patientennummer, so wie DataTables vorher im
 * Browser sortiert hat; die Kopfzellen sind Links mit ?sort=&dir=.
 */
final class MciBoardTest extends FeatureTestCase
{
    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function board_sortiert_nach_sichtung_und_nummer_und_ueber_die_kopfzellen(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['mci.manage'], 'cirs_username' => $user->username]);

        $lageId = (int) Capsule::table('intra_manv_lagen')->insertGetId([
            'einsatznummer' => 'MANV-' . uniqid(), 'einsatzort' => 'A1 km 42', 'status' => 'aktiv', 'erstellt_von' => $user->id,
        ]);
        foreach ([['P-02', 'SK3', 'Schulz'], ['P-03', 'SK1', 'Adler'], ['P-01', 'SK1', 'Müller'], ['P-04', 'SK2', 'Becker']] as [$nr, $sk, $name]) {
            Capsule::table('intra_manv_patienten')->insert([
                'manv_lage_id' => $lageId, 'patienten_nummer' => $nr, 'sichtungskategorie' => $sk, 'name' => $name, 'erstellt_von' => $user->id,
            ]);
        }

        $board = $this->get('/mci/board', ['query' => ['id' => (string) $lageId]]);
        $this->assertOk($board);
        $this->assertBodyNotContains('DataTable(', $board);
        $body = $board->body;
        // SK1 vor SK2 vor SK3, innerhalb von SK1 nach Nummer.
        $this->assertLessThan($this->pos($body, '>P-03<'), $this->pos($body, '>P-01<'));
        $this->assertLessThan($this->pos($body, '>P-04<'), $this->pos($body, '>P-03<'));
        $this->assertLessThan($this->pos($body, '>P-02<'), $this->pos($body, '>P-04<'));
        $this->assertBodyContains('aria-sort="ascending"><a class="ignis-table__sort is-asc" href="/mci/board?sort=sk&amp;dir=desc&amp;id=' . $lageId . '">SK', $board);
        $this->assertBodyContains('<a class="ignis-table__sort" href="/mci/board?sort=name&amp;dir=asc&amp;id=' . $lageId . '">Name', $board);
        $this->assertBodyContains('ignis-chip ignis-chip--sk1', $board);

        $byName = $this->get('/mci/board', ['query' => ['id' => (string) $lageId, 'sort' => 'name', 'dir' => 'desc']]);
        $body = $byName->body;
        $this->assertLessThan($this->pos($body, 'Müller'), $this->pos($body, 'Schulz'));
        $this->assertLessThan($this->pos($body, 'Becker'), $this->pos($body, 'Müller'));
        $this->assertLessThan($this->pos($body, 'Adler'), $this->pos($body, 'Becker'));
        $this->assertBodyContains('aria-sort="descending"><a class="ignis-table__sort is-desc" href="/mci/board?sort=name&amp;dir=asc&amp;id=' . $lageId . '">Name', $byName);

        // Unbekannte Spalte fällt auf den Standard zurück.
        $unknown = $this->get('/mci/board', ['query' => ['id' => (string) $lageId, 'sort' => 'geburtsdatum', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($unknown->body, '>P-02<'), $this->pos($unknown->body, '>P-01<'));
    }
}
