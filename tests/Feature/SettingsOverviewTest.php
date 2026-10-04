<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * /settings/index (Alias /settings) bündelt die placement=settings-Gruppen
 * aus config/navigation.php als Kacheln, dieselbe rechte-gefilterte Liste
 * (App\Helpers\Navigation::groups()), die auch die Sidebar-Zeile
 * „Einstellungen" ein- oder ausblendet, damit beide nie auseinanderlaufen.
 */
final class SettingsOverviewTest extends FeatureTestCase
{
    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    #[Test]
    public function mit_vollen_rechten_zeigt_sie_alle_abschnitte(): void
    {
        $this->login();

        $response = $this->get('/settings/index');

        $this->assertOk($response);
        $this->assertBodyContains('<h1>Einstellungen</h1>', $response);
        $this->assertBodyContains('<h2>Personal</h2>', $response);
        $this->assertBodyContains('<h2>Zugang</h2>', $response);
        $this->assertBodyContains('<h2>System</h2>', $response);
        $this->assertBodyContains('href="/settings/personnel/ranks/index"', $response);
        $this->assertBodyContains('href="/users/roles/index"', $response);
        $this->assertOk($this->get('/settings'));
    }

    #[Test]
    public function ohne_rechte_kommt_der_leerzustand(): void
    {
        $this->login([]);

        $response = $this->get('/settings/index');

        $this->assertOk($response);
        $this->assertBodyContains('Keine Bereiche freigeschaltet', $response);
        $this->assertBodyNotContains('twplus-link-card', $response);
    }

    #[Test]
    public function nur_kacheln_mit_passendem_recht_erscheinen(): void
    {
        $this->login(['personnel.view']);

        $response = $this->get('/settings/index');

        $this->assertOk($response);
        $this->assertBodyContains('<h2>Personal</h2>', $response);
        $this->assertBodyNotContains('<h2>Zugang</h2>', $response);
        $this->assertBodyNotContains('<h2>System</h2>', $response);
    }

    #[Test]
    public function die_sidebar_zeigt_einstellungen_nur_mit_einer_kachel(): void
    {
        $this->login(['personnel.view']);
        $this->assertBodyContains('href="/settings/index"', $this->get('/index'));

        $this->login([]);
        $this->assertBodyNotContains('href="/settings/index"', $this->get('/index'));
    }

    #[Test]
    public function eine_settings_unterseite_markiert_einstellungen_als_aktuell(): void
    {
        $this->login();

        $response = $this->get('/settings/personnel/ranks/index');

        $this->assertOk($response);
        $this->assertMatchesRegularExpression('~href="/settings/index"[^>]*aria-current="page"~', $response->body);
    }

    #[Test]
    public function enotf_unterseiten_markieren_einstellungen_als_aktuell(): void
    {
        $this->login();

        foreach (['/settings/enotf/kategorien/index'] as $path) {
            $response = $this->get($path);
            $this->assertOk($response);
            $this->assertMatchesRegularExpression('~href="/settings/index"[^>]*aria-current="page"~', $response->body, $path);
        }
    }

    #[Test]
    public function das_neu_menue_behaelt_seine_ziele_aus_den_verschobenen_gruppen(): void
    {
        $this->login();

        $dashboard = $this->get('/index');

        $this->assertBodyContains('data-quick-action-target="dienstgrad-create"', $dashboard);
        $this->assertBodyContains('data-quick-action-target="role-create"', $dashboard);
    }

    #[Test]
    public function sechs_abschnitte_in_fester_reihenfolge(): void
    {
        $this->login();

        $body = $this->get('/settings/index')->body;

        preg_match_all('~<h2>([^<]+)</h2>~', $body, $headings);
        $this->assertSame(['Personal', 'Zugang', 'Inhalte', 'eNOTF', 'Mail', 'System'], $headings[1]);
        $this->assertBodyContains('Einladungen', $this->get('/settings/index'));
        $this->assertStringNotContainsString('Registrierungscodes', $body);
        // Die Konfiguration hat eine eigene Kachel, der Rest des Systembereichs heißt Wartung und Diagnose.
        $this->assertMatchesRegularExpression('~href="/settings/system/config" class="twplus-link-card"~', $body);
        $this->assertStringContainsString('Wartung und Diagnose', $body);
        // Beladelisten stehen jetzt unter Inhalte, nicht mehr in einem eigenen Abschnitt.
        $this->assertMatchesRegularExpression('~<h2>Inhalte</h2>(?:(?!<h2>).)*Beladelisten~s', $body);
    }

    #[Test]
    public function die_suche_filtert_kacheln_nach_titel_und_beschreibung(): void
    {
        $this->login();

        $response = $this->get('/settings/index');

        $this->assertBodyContains('<div class="ignis-list-toolbar" role="search">', $response);
        $this->assertBodyContains('<input class="ignis-input" type="search" id="settings-search"', $response);
        $this->assertBodyContains('<section class="mb-5" data-settings-section>', $response);
        // Suchtext je Kachel: Titel, Beschreibung und Abschnitt, klein geschrieben.
        $this->assertBodyContains('data-settings-search="dienstgrade dienstgradstufen für mitarbeiter pflegen. personal"', $response);
        // Leerzustand ohne Treffer: da, aber versteckt, bis die Suche nichts findet.
        $this->assertBodyContains('<div id="settings-search-empty" role="status" hidden>', $response);
        $this->assertBodyContains('Keine Einstellung gefunden', $response);
        $this->assertBodyContains('class="ignis-empty__term"', $response);
    }

    #[Test]
    public function ohne_systemdaten_zeigt_sie_den_einrichtungshinweis(): void
    {
        $this->setConfig(['SYSTEM_URL' => 'CHANGE_ME', 'SERVER_NAME' => 'CHANGE_ME']);
        $this->login();

        $response = $this->get('/settings/index');

        $this->assertBodyContains('id="setup-notice"', $response);
        $this->assertBodyContains('Noch offen: System-URL, Servername.', $response);
        $this->assertBodyContains('href="/settings/system/config?setup=1">Jetzt einrichten</a>', $response);
    }

    #[Test]
    public function mit_systemdaten_oder_ohne_systemrecht_kein_hinweis(): void
    {
        $this->setConfig(['SYSTEM_URL' => 'CHANGE_ME', 'SERVER_NAME' => 'CHANGE_ME']);
        $this->login(['personnel.view']);
        $this->assertBodyNotContains('setup-notice', $this->get('/settings/index'));

        $this->setConfig(['SYSTEM_URL' => 'intra.example.de', 'SERVER_NAME' => 'Rheinstadt RP']);
        $this->login();
        $this->assertBodyNotContains('setup-notice', $this->get('/settings/index'));
    }

    /** @param array<string, string> $values */
    private function setConfig(array $values): void
    {
        foreach ($values as $key => $value) {
            Capsule::table('intra_config')->where('config_key', $key)->update(['config_value' => $value]);
        }
        (new \ReflectionProperty(\App\Config\ConfigManager::class, 'configCache'))->setValue(null, null);
    }
}
