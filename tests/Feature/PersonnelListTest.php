<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Personnel;
use App\Models\Rank;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Mitarbeiterliste (PersonnelController::index) sortiert, sucht,
 * filtert und blättert auf dem Server; der CSV-Export nimmt denselben
 * Filterstand. Dienstgrad und Qualifikationen sortieren nach Priorität.
 */
final class PersonnelListTest extends FeatureTestCase
{
    private Rank $rankLow;
    private Rank $rankHigh;
    private AmbSkill $rdNone;
    private AmbSkill $rdSome;
    private FdSkill $fwNone;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $this->rankLow  = $this->rank('Anwärter', 10);
        $this->rankHigh = $this->rank('Brandmeister', 90);
        $this->rdNone   = $this->amb('Keine', 0, true);
        $this->rdSome   = $this->amb('Notfallsanitäter', 50, false);
        $this->fwNone   = $this->fd('Keine', 0, true);
    }

    private function rank(string $name, int $priority): Rank
    {
        $rank = new Rank();
        $rank->name     = $name . '_' . uniqid();
        $rank->name_m   = $rank->name;
        $rank->name_w   = $rank->name;
        $rank->priority = $priority;
        $rank->archive  = false;
        $rank->save();

        return $rank;
    }

    private function amb(string $name, int $priority, bool $none): AmbSkill
    {
        $skill = new AmbSkill();
        $skill->name     = $name . '_' . uniqid();
        $skill->name_m   = $skill->name;
        $skill->name_w   = $skill->name;
        $skill->priority = $priority;
        $skill->none     = $none;
        $skill->save();

        return $skill;
    }

    private function fd(string $name, int $priority, bool $none): FdSkill
    {
        $skill = new FdSkill();
        $skill->name      = $name . '_' . uniqid();
        $skill->shortname = strtoupper(substr($name, 0, 2));
        $skill->name_m    = $skill->name;
        $skill->name_w    = $skill->name;
        $skill->priority  = $priority;
        $skill->none      = $none;
        $skill->save();

        return $skill;
    }

    private function person(string $name, string $dienstnr, Rank $rank, ?AmbSkill $rd = null, string $einstdatum = '2024-01-01'): Personnel
    {
        $m = new Personnel();
        $m->fullname   = $name;
        $m->dienstnr   = $dienstnr;
        $m->gebdatum   = new \DateTime('1990-01-01');
        $m->geschlecht = 0;
        $m->einstdatum = new \DateTime($einstdatum);
        $m->dienstgrad = $rank->id;
        $m->qualird    = ($rd ?? $this->rdNone)->id;
        $m->qualifw2   = $this->fwNone->id;
        $m->save();

        return $m;
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function sortiert_nach_name_und_dienstgrad_prioritaet(): void
    {
        $this->person('Zoe Zuletzt', 'T-901', $this->rankLow, null, '2020-01-01');
        $this->person('Adam Anfang', 'T-902', $this->rankHigh, null, '2021-01-01');

        $default = $this->get('/personnel/list', ['query' => ['q' => 'T-90']]);
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertBodyContains('aria-sort="ascending"><a class="ignis-table__sort is-asc" href="/personnel/list?q=T-90&amp;sort=einstdatum&amp;dir=desc">Einstellungsdatum', $default);
        $this->assertLessThan($this->pos($default->body, 'Adam Anfang'), $this->pos($default->body, 'Zoe Zuletzt'));

        $byName = $this->get('/personnel/list', ['query' => ['q' => 'T-90', 'sort' => 'name', 'dir' => 'asc']]);
        $this->assertLessThan($this->pos($byName->body, 'Zoe Zuletzt'), $this->pos($byName->body, 'Adam Anfang'));

        $byRank = $this->get('/personnel/list', ['query' => ['q' => 'T-90', 'sort' => 'dienstgrad', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($byRank->body, 'Zoe Zuletzt'), $this->pos($byRank->body, 'Adam Anfang'), 'Höhere Priorität zuerst.');

        $unknown = $this->get('/personnel/list', ['query' => ['q' => 'T-90', 'sort' => 'gebdatum']]);
        $this->assertBodyContains('aria-sort="ascending"><a class="ignis-table__sort is-asc" href="/personnel/list?q=T-90&amp;sort=einstdatum&amp;dir=desc">Einstellungsdatum', $unknown);
    }

    #[Test]
    public function suche_und_filter_nach_dienstgrad_und_qualifikation(): void
    {
        $this->person('Filter Eins', 'F-001', $this->rankLow, $this->rdSome);
        $this->person('Filter Zwei', 'F-002', $this->rankHigh);
        $this->person('Ohne Treffer', 'X-003', $this->rankHigh);

        $found = $this->get('/personnel/list', ['query' => ['q' => 'Filter']]);
        $this->assertBodyContains('Filter Eins', $found);
        $this->assertBodyContains('Filter Zwei', $found);
        $this->assertBodyNotContains('Ohne Treffer', $found);
        $this->assertBodyContains('2 von 2 Mitarbeiter', $found);

        $byNumber = $this->get('/personnel/list', ['query' => ['q' => 'X-00']]);
        $this->assertBodyContains('Ohne Treffer', $byNumber);
        $this->assertBodyNotContains('Filter Eins', $byNumber);

        $byRank = $this->get('/personnel/list', ['query' => ['q' => 'Filter', 'dg' => (string) $this->rankHigh->id]]);
        $this->assertBodyContains('Filter Zwei', $byRank);
        $this->assertBodyNotContains('Filter Eins', $byRank);
        $this->assertBodyContains('value="' . $this->rankHigh->id . '" selected', $byRank);

        $byQuali = $this->get('/personnel/list', ['query' => ['q' => 'Filter', 'rd' => (string) $this->rdSome->id]]);
        $this->assertBodyContains('Filter Eins', $byQuali);
        $this->assertBodyNotContains('Filter Zwei', $byQuali);
    }

    #[Test]
    public function seite_zwei_und_csv_export_mit_demselben_filter(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->person(sprintf('Seite %02d', $i), sprintf('S-%03d', $i), $this->rankLow, null, sprintf('2023-01-%02d', $i));
        }

        $first = $this->get('/personnel/list', ['query' => ['q' => 'S-0']]);
        $this->assertBodyContains('Seite 25', $first);
        $this->assertBodyNotContains('Seite 26', $first);
        $this->assertBodyContains('1–25 von 26 Mitarbeiter', $first);

        $second = $this->get('/personnel/list', ['query' => ['q' => 'S-0', 'page' => '2']]);
        $this->assertBodyContains('Seite 26', $second);
        $this->assertBodyNotContains('Seite 25', $second);

        $csv = $this->get('/personnel/list', ['query' => ['q' => 'S-0', 'export' => 'csv']]);
        $this->assertOk($csv);
        $this->assertStringContainsString('text/csv', $csv->headers['Content-Type'] ?? '');
        $this->assertSame(27, substr_count($csv->body, "\n"), 'Kopfzeile plus alle 26 Treffer, nicht nur die Seite.');
        $this->assertStringContainsString('"S-026";"Seite 26"', $csv->body);
        $this->assertStringNotContainsString('<html', $csv->body);
    }
}
