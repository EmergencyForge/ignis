<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Rank;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Einstellungsseiten auf den ignis-Bausteinen: Dashboard-Konfiguration,
 * Dokumenten-Kategorien, Dienstgrade, System-Konfiguration, Cron-Jobs,
 * Fehlerprotokoll und Performance rendern mit ignis-Tabellen, Chips und
 * den Filter-Leisten, ohne Bootstrap-Klassen.
 */
final class SystemPagesTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    #[Test]
    public function dashboard_konfiguration_als_karten_mit_stapel(): void
    {
        $categoryId = (int) Capsule::table('intra_dashboard_categories')->insertGetId(['title' => 'Einsatz', 'priority' => 1]);
        Capsule::table('intra_dashboard_tiles')->insert(['category' => $categoryId, 'title' => 'Fahrzeuge', 'url' => '/settings/vehicles/vehicles/index', 'icon' => 'fa-solid fa-truck', 'priority' => 1]);

        $page = $this->get('/settings/dashboard/index');

        $this->assertOk($page);
        $this->assertBodyContains('<title>Dashboard-Konfiguration', $page);
        $this->assertBodyContains('<h2 class="ignis-card__title">Einsatz <span class="ignis-card__subtitle">Priorität 1 · 1 Verlinkungen</span></h2>', $page);
        $this->assertBodyContains('<ol class="twplus-stacked-list">', $page);
        $this->assertBodyContains('id="tile-icon-suggestions"', $page);
        $this->assertBodyNotContains('input-group', $page);
    }

    #[Test]
    public function dokumenten_kategorien_als_tabelle(): void
    {
        // Eine Zeile aus der Zeit vor den Farbschlüsseln, zwei mit Schlüssel:
        // der alte Klassenname und der Schlüssel rendern denselben Chip.
        Capsule::table('intra_dokument_kategorien')->insert([
            ['name' => 'Bescheinigung', 'color' => 'ignis-chip--success', 'icon' => 'fa-solid fa-scroll', 'sort_order' => 2],
            ['name' => 'Nachweis',      'color' => 'ok',                  'icon' => null,                 'sort_order' => 3],
            ['name' => 'Mahnung',       'color' => 'danger',              'icon' => null,                 'sort_order' => 4],
        ]);

        $page = $this->get('/settings/documents/categories');

        $this->assertOk($page);
        $this->assertBodyContains('<table class="ignis-table" id="categoryTable">', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--ok">Bescheinigung</span>', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--ok">Nachweis</span>', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--danger">Mahnung</span>', $page);
        // Das Bearbeiten-Formular bekommt den Schlüssel, nicht den alten Klassennamen.
        $this->assertBodyContains('&quot;name&quot;:&quot;Bescheinigung&quot;,&quot;color&quot;:&quot;ok&quot;', $page);
        $this->assertBodyContains('<option value="ok">Grün</option>', $page);
        $this->assertBodyNotContains('<option value="ignis-chip--', $page);
        $this->assertBodyContains('class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Löschen"', $page);
        $this->assertBodyNotContains('table-striped', $page);
    }

    #[Test]
    public function dienstgrade_mit_chips_und_zeilenaktionen(): void
    {
        $rank = new Rank();
        $rank->name = 'Oberbrandmeister'; $rank->name_m = 'Oberbrandmeister'; $rank->name_w = 'Oberbrandmeisterin'; $rank->priority = 30; $rank->archive = true;
        $rank->save();

        $page = $this->get('/settings/personnel/ranks/index');

        $this->assertOk($page);
        $this->assertBodyContains('<tr class="is-muted">', $page);
        $this->assertBodyContains("<span class='ignis-chip ignis-chip--dot ignis-chip--danger'>Ja</span>", $page);
        $this->assertBodyContains('<td class="ignis-table__actions"><div class="ignis-row-actions"><button type=\'button\' class=\'ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon\'', $page);
        $this->assertBodyContains('<img id="dienstgrad-badge-preview" src="" alt="Vorschau des Badges" class="h-8 w-auto shrink-0" hidden>', $page);
        $this->assertBodyNotContains('badge-status', $page);
        $this->assertBodyNotContains('input-group', $page);
    }

    #[Test]
    public function system_konfiguration_mit_kategoriefilter(): void
    {
        $page = $this->get('/settings/system/config');

        $this->assertOk($page);
        $this->assertBodyContains('<nav class="ignis-filter-links" id="categoryFilter" aria-label="Kategorie">', $page);
        $this->assertBodyContains('<button type="button" class="is-active" data-category="">Alle</button>', $page);
        $this->assertBodyContains('name="save_config" class="ignis-btn ignis-btn--primary"', $page);
        $this->assertBodyNotContains('form-select', $page);
        $this->assertBodyNotContains('input-group', $page);
        $this->assertBodyNotContains('btn-toolbar-group', $page);
    }

    #[Test]
    public function cron_jobs_als_tabelle(): void
    {
        Capsule::table('intra_cron_jobs')->insert([
            'identifier' => 'test.weekly', 'name' => 'Wochenstatistik', 'handler_type' => 'webhook', 'handler' => 'https://example.test/hook',
            'schedule' => '0 8 * * 1', 'last_status' => 'failed', 'fail_count' => 2, 'active' => 1, 'is_builtin' => 0,
        ]);

        $page = $this->get('/settings/system/cron');

        $this->assertOk($page);
        $this->assertBodyContains('<table class="ignis-table" id="table-cron-jobs">', $page);
        $this->assertBodyContains('Wochenstatistik', $page);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--dot ignis-chip--danger">Fehler</span>', $page);
        $this->assertBodyContains('<td class="ignis-table__num">2</td>', $page);
        $this->assertBodyContains('<select name="handler_type" id="cron-handler-type" class="ignis-input" required>', $page);
        $this->assertBodyNotContains('badge-status', $page);
        $this->assertBodyNotContains('form-select', $page);
    }

    #[Test]
    public function fehlerprotokoll_und_performance_rendern(): void
    {
        $logs = $this->get('/settings/system/logs');
        $this->assertOk($logs);
        $this->assertBodyContains('<title>Fehlerprotokoll', $logs);
        $this->assertBodyContains('<nav class="ignis-filter-links" id="inboxScopeFilter" aria-label="Stufe">', $logs);
        $this->assertBodyContains('<select id="searchFile" class="ignis-input">', $logs);
        $this->assertBodyContains('<table class="ignis-table" id="table-log-files">', $logs);
        $this->assertBodyNotContains('input-group', $logs);
        $this->assertBodyNotContains('badge-status', $logs);

        $perf = $this->get('/settings/system/performance');
        $this->assertOk($perf);
        $this->assertBodyContains('<title>Performance', $perf);
        $this->assertBodyContains('<table class="ignis-table" id="table-performance">', $perf);
        $this->assertBodyContains('<div class="ignis-detail__block">', $perf);
        $this->assertBodyContains('ignis-skeleton', $perf);
        $this->assertBodyNotContains('perf-card', $perf);
    }
}
