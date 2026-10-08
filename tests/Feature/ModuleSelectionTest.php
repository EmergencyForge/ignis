<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\ModuleSelection;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Einstellungen › System › Module: Auswahl der mitgelieferten Module und
 * der erste Schritt der Einrichtungs-Checkliste. Die Transaktion rollt
 * Plugin-Schalter und Konfiguration nach jedem Test zurück.
 */
final class ModuleSelectionTest extends FeatureTestCase
{
    /** @param list<string> $permissions */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    private function setDone(bool $done): void
    {
        Capsule::table('intra_config')->where('config_key', ModuleSelection::DONE_KEY)->update(['config_value' => $done ? 'true' : 'false']);
    }

    private function enabled(string $pluginId): bool
    {
        return (bool) Capsule::table('intra_plugins')->where('plugin_id', $pluginId)->value('enabled');
    }

    /** @return list<string> alle wählbaren Module dieser Installation */
    private function allModules(): array
    {
        return array_column(ModuleSelection::fromDirectory()->modules(), 'id');
    }

    #[Test]
    public function die_seite_zeigt_die_module_und_beim_ersten_start_den_hinweis(): void
    {
        $this->setDone(false);
        $this->login();

        $page = $this->get('/settings/system/modules');

        $this->assertOk($page);
        // Die Attribute stehen im Template je auf einer Zeile.
        $this->assertMatchesRegularExpression('~name="modules\[\]"\s+value="calendar"~', $page->body);
        $this->assertMatchesRegularExpression('~name="modules\[\]"\s+value="forms"~', $page->body);
        $this->assertBodyContains('id="modules-first-run"', $page);
        // Grundmodule stehen nicht zur Wahl.
        $this->assertBodyNotContains('value="personnel"', $page);
    }

    #[Test]
    public function speichern_schaltet_ab_und_erledigt_den_schritt(): void
    {
        $this->setDone(false);
        $this->login();
        $selected = array_values(array_diff($this->allModules(), ['calendar']));

        $response = $this->post('/settings/system/modules', ['modules' => $selected]);

        $this->assertRedirect($response, '/index');
        $this->assertFalse($this->enabled('calendar'));
        $this->assertTrue($this->enabled('forms'));
        $this->assertTrue(ModuleSelection::isDone());
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('action', 'Plugin deaktiviert')->where('details', 'Kalender')->count());
    }

    #[Test]
    public function enotf_v2_ohne_enotf_wird_abgelehnt(): void
    {
        $this->setDone(false);
        $this->login();
        $before = $this->enabled('enotf');
        $selected = array_values(array_diff($this->allModules(), ['enotf']));

        $response = $this->post('/settings/system/modules', ['modules' => $selected]);

        $this->assertRedirect($response, '/settings/system/modules');
        $this->assertSame($before, $this->enabled('enotf'));
        $this->assertFalse(ModuleSelection::isDone());
    }

    #[Test]
    public function ohne_adminrecht_kein_zugang(): void
    {
        $this->login(['personnel.view']);

        $response = $this->get('/settings/system/modules');

        $this->assertRedirect($response);
    }

    #[Test]
    public function auf_dem_dashboard_ist_die_modulauswahl_der_erste_schritt(): void
    {
        $this->setDone(false);
        $this->login();

        $page = $this->get('/index');

        $this->assertBodyContains('Module auswählen', $page);
        $this->assertBodyContains('href="/settings/system/modules">Jetzt einrichten</a>', $page);
    }
}
