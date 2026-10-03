<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        $this->assertBodyContains('<h2>Personal &amp; Qualifikationen</h2>', $response);
        $this->assertBodyContains('<h2>Zugang &amp; Rechte</h2>', $response);
        $this->assertBodyContains('<h2>System &amp; Integrationen</h2>', $response);
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
        $this->assertBodyContains('<h2>Personal &amp; Qualifikationen</h2>', $response);
        $this->assertBodyNotContains('<h2>Zugang &amp; Rechte</h2>', $response);
        $this->assertBodyNotContains('<h2>System &amp; Integrationen</h2>', $response);
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
}
