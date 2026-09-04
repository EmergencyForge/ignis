<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogbookEntry;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Fahrtenbuch der Verwaltung (LogbookController::index): Werkzeugleiste
 * mit Fahrzeug, Fahrttyp und Zeitraum, die Fahrten als ignis-Tabelle mit
 * Chip je Fahrttyp und Zeilenaktionen nur für logbook.manage.
 */
final class LogbookListTest extends FeatureTestCase
{
    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    private function fahrt(string $fahrer, string $typ, int $vehicleId, string $identifier): LogbookEntry
    {
        return LogbookEntry::query()->create([
            'vehicle_id'         => $vehicleId,
            'vehicle_identifier' => $identifier,
            'datum'              => '2026-02-10',
            'abfahrt'            => '08:15:00',
            'ankunft'            => '09:00:00',
            'stationierungsort'  => 'Wache 1',
            'kilometer'          => 12.5,
            'grund'              => 'Brandmeldeanlage',
            'fahrttyp'           => $typ,
            'fahrer_name'        => $fahrer,
            'source'             => 'admin',
        ]);
    }

    #[Test]
    public function liste_mit_werkzeugleiste_tabelle_und_zeilenaktionen(): void
    {
        $this->login(['logbook.view', 'logbook.manage']);
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Log 1/44/1']);
        $this->fahrt('Lena Logbuch', 'einsatzfahrt', $vehicle['id'], $vehicle['identifier']);

        $page = $this->get('/logbook/index');

        $this->assertOk($page);
        $this->assertBodyContains('<title>Fahrtenbuch', $page);
        $this->assertBodyContains('<form method="GET" action="/logbook/index" class="ignis-list-toolbar" role="search">', $page);
        $this->assertBodyContains('<label class="ignis-list-toolbar__field">', $page);
        $this->assertBodyContains('<table class="ignis-table" id="fahrtenbuchAdminTable">', $page);
        $this->assertBodyContains('Lena Logbuch', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--danger">Einsatzfahrt</span>', $page);
        $this->assertBodyContains('<td class="ignis-table__num">12,5</td>', $page);
        $this->assertBodyContains('class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon fb-edit-btn"', $page);
        $this->assertBodyContains('<select class="ignis-input ignis-input--sm" data-custom-dropdown="true" id="fb_fahrzeug" name="vehicle_id" required>', $page);
        $this->assertBodyNotContains('form-select', $page);
        $this->assertBodyNotContains('input-group', $page);
    }

    #[Test]
    public function nur_lesen_zeigt_keine_zeilenaktionen(): void
    {
        $this->login(['logbook.view']);
        $vehicle = FixtureFactory::fahrzeug();
        $this->fahrt('Rita Readonly', 'dienstfahrt', $vehicle['id'], $vehicle['identifier']);

        $page = $this->get('/logbook/index');

        $this->assertOk($page);
        $this->assertBodyContains('Rita Readonly', $page);
        $this->assertBodyNotContains('ignis-btn--icon fb-edit-btn', $page);
        $this->assertBodyNotContains('ignis-row-actions', $page);
        $this->assertBodyNotContains('id="toggleCreateForm"', $page);
    }
}
