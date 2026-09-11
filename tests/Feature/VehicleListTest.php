<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Fahrzeugliste (Settings\FahrzeugeController::index) sortiert, sucht
 * und blättert auf dem Server. Die Abfrage lag vorher im Template und
 * DataTables sortierte im Browser.
 */
final class VehicleListTest extends FeatureTestCase
{
    private const PATH = '/settings/vehicles/vehicles/index';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function standard_ist_prioritaet_aufsteigend_und_spalten_sortieren(): void
    {
        FixtureFactory::fahrzeug(['name' => 'Sortier Zulu', 'priority' => 5]);
        FixtureFactory::fahrzeug(['name' => 'Sortier Alpha', 'priority' => 7]);

        $default = $this->get(self::PATH, ['query' => ['q' => 'Sortier']]);
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertBodyContains('aria-sort="ascending" class="ignis-table__num"><a class="ignis-table__sort is-asc" href="/settings/vehicles/vehicles/index?q=Sortier&amp;sort=priority&amp;dir=desc">Priorität', $default);
        $this->assertLessThan($this->pos($default->body, 'Sortier Alpha'), $this->pos($default->body, 'Sortier Zulu'));

        $byName = $this->get(self::PATH, ['query' => ['q' => 'Sortier', 'sort' => 'name', 'dir' => 'asc']]);
        $this->assertLessThan($this->pos($byName->body, 'Sortier Zulu'), $this->pos($byName->body, 'Sortier Alpha'));

        $unknown = $this->get(self::PATH, ['query' => ['q' => 'Sortier', 'sort' => 'identifier', 'dir' => 'desc']]);
        $this->assertBodyContains('is-asc" href="/settings/vehicles/vehicles/index?q=Sortier&amp;sort=priority&amp;dir=desc">Priorität', $unknown);
    }

    #[Test]
    public function suche_ueber_bezeichnung_kennzeichen_und_typ(): void
    {
        $rtw = FixtureFactory::fahrzeug(['name' => 'Florian Test 83/1', 'rd_type' => 2]);
        FixtureFactory::fahrzeug(['name' => 'Florian Test 83/2', 'rd_type' => 3]);
        $this->pdo->exec("UPDATE intra_fahrzeuge SET kennzeichen = 'LS-FW 831' WHERE id = " . $rtw['id']);

        $byName = $this->get(self::PATH, ['query' => ['q' => 'Florian Test']]);
        $this->assertBodyContains('Florian Test 83/1', $byName);
        $this->assertBodyContains('Florian Test 83/2', $byName);
        $this->assertBodyContains('2 von 2 Fahrzeuge', $byName);

        $byPlate = $this->get(self::PATH, ['query' => ['q' => 'LS-FW 83']]);
        $this->assertBodyContains('<span class="ignis-mono">LS-FW 831</span>', $byPlate);
        $this->assertBodyNotContains('Florian Test 83/2', $byPlate);

        $byType = $this->get(self::PATH, ['query' => ['q' => 'LHF']]);
        $this->assertBodyContains('Florian Test 83/2', $byType);
        $this->assertBodyNotContains('Florian Test 83/1', $byType);
    }

    #[Test]
    public function seite_zwei(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            FixtureFactory::fahrzeug(['name' => sprintf('Seite %02d', $i), 'priority' => $i]);
        }

        $first = $this->get(self::PATH, ['query' => ['q' => 'Seite ']]);
        $this->assertBodyContains('Seite 25', $first);
        $this->assertBodyNotContains('Seite 26', $first);
        $this->assertBodyContains('1–25 von 26 Fahrzeuge', $first);

        $second = $this->get(self::PATH, ['query' => ['q' => 'Seite ', 'page' => '2']]);
        $this->assertBodyContains('Seite 26', $second);
        $this->assertBodyNotContains('Seite 25', $second);
        $this->assertBodyContains('aria-current="page">2<', $second);
    }
}
