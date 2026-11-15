<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Flash;
use App\Models\User;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use App\Support\DeskShell;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Sprache pro Konto: POST /profile/locale nimmt nur Sprachen aus
 * APP_LOCALES, wirkt sofort über die Session und dauerhaft über
 * intra_users.locale. Ohne eigene Wahl gilt APP_LOCALE, auch ohne Login.
 */
final class LocaleTest extends FeatureTestCase
{
    /** @var array<string, string|null> */
    private array $envBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['APP_LOCALE', 'APP_LOCALES'] as $key) {
            $this->envBefore[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBefore as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        parent::tearDown();
    }

    private function login(): User
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => []]);

        return $user;
    }

    /**
     * @return array<string,mixed>
     */
    private function form(string $locale): array
    {
        return ['locale' => $locale, 'csrf_token' => CsrfProtection::getToken()];
    }

    private function assertHtmlLang(string $expected, string $body): void
    {
        $this->assertMatchesRegularExpression('~<html\b[^>]*\blang="' . preg_quote($expected, '~') . '"~', $body);
    }

    #[Test]
    public function standard_ist_deutsch(): void
    {
        $this->login();

        $this->assertHtmlLang('de', $this->get('/dashboard')->body);
    }

    #[Test]
    public function installationssprache_gilt_auch_ohne_login(): void
    {
        $_ENV['APP_LOCALE'] = 'en-GB';

        $this->assertHtmlLang('en-GB', $this->get('/login')->body);
    }

    #[Test]
    public function englisch_wird_gespeichert_und_sofort_gerendert(): void
    {
        $_ENV['APP_LOCALES'] = 'de,en-GB';
        $user = $this->login();

        $response = $this->post('/profile/locale', $this->form('en-GB'), [
            'headers' => ['Referer' => 'http://localhost/personnel/list', 'Host' => 'localhost'],
        ]);

        $this->assertRedirect($response, '/personnel/list');
        $this->assertSame('en-GB', User::query()->findOrFail($user->id)->locale);
        $this->assertSame('en-GB', $_SESSION['locale']);
        $this->assertHtmlLang('en-GB', $this->get('/dashboard')->body);
    }

    #[Test]
    public function nicht_freigeschaltete_sprache_wird_abgewiesen(): void
    {
        $user = $this->login();

        $response = $this->post('/profile/locale', $this->form('en-GB'));

        $this->assertRedirect($response, 'index');
        $this->assertNull(User::query()->findOrFail($user->id)->locale);
        $this->assertArrayNotHasKey('locale', $_SESSION);
        $this->assertSame('Diese Sprache ist nicht freigeschaltet.', Flash::get()['text'] ?? null);
    }

    #[Test]
    public function mit_falschem_csrf_token_aendert_sich_nichts(): void
    {
        $_ENV['APP_LOCALES'] = 'de,en-GB';
        $user = $this->login();

        $response = $this->post('/profile/locale', ['locale' => 'en-GB', 'csrf_token' => 'falsch']);

        $this->assertStatus(403, $response);
        $this->assertNull(User::query()->findOrFail($user->id)->locale);
    }

    #[Test]
    public function ohne_login_geht_es_zur_anmeldung(): void
    {
        $response = $this->post('/profile/locale', ['locale' => 'en-GB']);

        $this->assertRedirect($response, 'login');
    }

    #[Test]
    public function sprache_ueberlebt_den_naechsten_login(): void
    {
        $_ENV['APP_LOCALES'] = 'de,en-GB';
        $user = $this->login();
        $this->post('/profile/locale', $this->form('en-GB'));

        SessionManager::loginUser(User::query()->findOrFail($user->id)->toArray(), []);

        $this->assertSame('en-GB', $_SESSION['locale']);
    }

    #[Test]
    public function wieder_gesperrte_sprache_faellt_auf_die_installation_zurueck(): void
    {
        $this->login();
        $_SESSION['locale'] = 'en-GB';

        $this->assertHtmlLang('de', $this->get('/dashboard')->body);
    }

    #[Test]
    public function kontrollzentrum_ohne_sprachwahl_bei_einer_sprache(): void
    {
        $this->login();

        $this->assertNull(DeskShell::config()['locale']);
    }

    #[Test]
    public function kontrollzentrum_zeigt_die_sprachwahl_ab_zwei_sprachen(): void
    {
        $this->login();
        $_ENV['APP_LOCALES'] = 'de,en-GB';
        $_SESSION['locale'] = 'en-GB';
        $locale = DeskShell::config()['locale'];

        $this->assertSame('/profile/locale', $locale['action']);
        $this->assertSame('en-GB', $locale['current']);
        $this->assertSame([['de', 'Deutsch'], ['en-GB', 'English']], $locale['options']);
    }
}
