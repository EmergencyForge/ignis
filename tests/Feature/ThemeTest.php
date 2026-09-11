<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Darstellungsmodus pro Konto: POST /profile/theme wirkt sofort auf die
 * Session und dauerhaft auf intra_users.theme, die Hülle schreibt den Wert
 * als data-theme ans <html>, bevor ein Stylesheet lädt. Unbekannte Werte und fehlende
 * CSRF-Tokens ändern nichts; „system" überlässt die Wahl dem Browser.
 */
final class ThemeTest extends FeatureTestCase
{
    private function login(): User
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => []]);

        return $user;
    }

    /**
     * @return array<string,mixed>
     */
    private function form(string $theme): array
    {
        return ['theme' => $theme, 'csrf_token' => CsrfProtection::getToken()];
    }

    #[Test]
    public function standard_ist_dunkel(): void
    {
        $this->login();

        $this->assertBodyContains('<html lang="de" data-theme="dark">', $this->get('/dashboard'));
    }

    #[Test]
    public function hell_wird_gespeichert_und_sofort_gerendert(): void
    {
        $user = $this->login();

        $response = $this->post('/profile/theme', $this->form('light'), [
            'headers' => ['Referer' => 'http://localhost/personnel/list', 'Host' => 'localhost'],
        ]);

        $this->assertRedirect($response, '/personnel/list');
        $this->assertSame('light', User::query()->findOrFail($user->id)->theme);
        $this->assertSame('light', $_SESSION['theme']);
        $this->assertBodyContains('<html lang="de" data-theme="light">', $this->get('/dashboard'));
    }

    #[Test]
    public function system_ueberlaesst_die_wahl_dem_browser(): void
    {
        $this->login();

        $this->post('/profile/theme', $this->form('system'));
        $page = $this->get('/dashboard');

        $this->assertBodyContains("matchMedia('(prefers-color-scheme: light)')", $page);
    }

    #[Test]
    public function unbekannter_wert_wird_abgewiesen(): void
    {
        $user = $this->login();

        $response = $this->post('/profile/theme', $this->form('neon'));

        $this->assertRedirect($response, 'index');
        $this->assertSame('dark', User::query()->findOrFail($user->id)->theme);
        $this->assertArrayNotHasKey('theme', $_SESSION);
    }

    #[Test]
    public function ohne_csrf_token_aendert_sich_nichts(): void
    {
        $user = $this->login();

        $response = $this->post('/profile/theme', ['theme' => 'light']);

        $this->assertRedirect($response, 'index');
        $this->assertSame('dark', User::query()->findOrFail($user->id)->theme);
    }

    #[Test]
    public function ohne_login_geht_es_zur_anmeldung(): void
    {
        $response = $this->post('/profile/theme', ['theme' => 'light']);

        $this->assertRedirect($response, 'login');
    }

    #[Test]
    public function modus_ueberlebt_den_naechsten_login(): void
    {
        $user = $this->login();
        $this->post('/profile/theme', $this->form('light'));

        SessionManager::loginUser(User::query()->findOrFail($user->id)->toArray(), []);

        $this->assertSame('light', $_SESSION['theme']);
    }

    #[Test]
    public function alte_session_ohne_modus_liest_die_spalte(): void
    {
        $user = $this->login();
        $user->theme = 'light';
        $user->save();

        $this->assertBodyContains('<html lang="de" data-theme="light">', $this->get('/dashboard'));
        $this->assertSame('light', $_SESSION['theme']);
    }

    #[Test]
    public function fremder_referer_wird_nicht_verfolgt(): void
    {
        $this->login();

        $response = $this->post('/profile/theme', $this->form('light'), [
            'headers' => ['Referer' => 'https://evil.example/phish', 'Host' => 'localhost'],
        ]);

        $this->assertRedirect($response, 'index');
        $this->assertStringNotContainsString('evil.example', $response->headers['Location'] ?? '');
    }
}
