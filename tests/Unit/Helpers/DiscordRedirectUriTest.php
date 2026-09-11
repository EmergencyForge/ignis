<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\DiscordOAuth;
use App\Helpers\ProtocolDetection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Zwei Auswege gegen einen falschen Host hinter dem Proxy, hier beide
 * festgenagelt: X-Forwarded-Host, und DISCORD_REDIRECT_URI als letztes Wort.
 */
final class DiscordRedirectUriTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        unset($_ENV['DISCORD_REDIRECT_URI'], $_SERVER['DISCORD_REDIRECT_URI']);
        putenv('DISCORD_REDIRECT_URI');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        unset($_ENV['DISCORD_REDIRECT_URI'], $_SERVER['DISCORD_REDIRECT_URI']);
        putenv('DISCORD_REDIRECT_URI');

        parent::tearDown();
    }

    #[Test]
    public function ohne_proxy_bleibt_der_host_header_massgeblich(): void
    {
        $_SERVER['HTTP_HOST'] = 'intra.example.de';
        unset($_SERVER['HTTP_X_FORWARDED_HOST']);

        $this->assertSame('https://intra.example.de', $this->baseUrlOverHttps());
    }

    #[Test]
    public function hinter_dem_proxy_gewinnt_der_weitergereichte_host(): void
    {
        $_SERVER['HTTP_HOST'] = 'fabrica-ignis-kreis-nord';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'kreis-nord.example.de';

        $this->assertSame('https://kreis-nord.example.de', $this->baseUrlOverHttps());
    }

    #[Test]
    public function bei_einer_kette_zaehlt_der_erste_eintrag(): void
    {
        $_SERVER['HTTP_HOST'] = 'fabrica-ignis-kreis-nord';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'kreis-nord.example.de, innerer-proxy';

        $this->assertSame('https://kreis-nord.example.de', $this->baseUrlOverHttps());
    }

    #[Test]
    public function ein_port_im_weitergereichten_host_bleibt_erhalten(): void
    {
        $_SERVER['HTTP_HOST'] = 'fabrica-ignis-kreis-nord';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'kreis-nord.example.de:8443';

        $this->assertSame('https://kreis-nord.example.de:8443', $this->baseUrlOverHttps());
    }

    /**
     * Ein Host mit Schrägstrich ist kein Host. Wer so etwas schickt, will die
     * Redirect-URI umbiegen, nicht einen Proxy abbilden.
     */
    #[Test]
    public function unsinn_im_weitergereichten_host_faellt_zurueck_auf_host_header(): void
    {
        $_SERVER['HTTP_HOST'] = 'intra.example.de';

        foreach (['boese.example.de/pfad', 'mit leerzeichen', '', '   ', "zeile\numbruch"] as $unsinn) {
            $_SERVER['HTTP_X_FORWARDED_HOST'] = $unsinn;

            $this->assertSame(
                'https://intra.example.de',
                $this->baseUrlOverHttps(),
                'Abgelehnt werden sollte: ' . var_export($unsinn, true)
            );
        }
    }

    #[Test]
    public function ohne_variable_wird_die_uri_weiterhin_hergeleitet(): void
    {
        $_SERVER['HTTP_HOST'] = 'intra.example.de';
        $_SERVER['HTTPS'] = 'on';

        $this->assertSame(
            ProtocolDetection::buildRedirectUri('auth/callback.php'),
            DiscordOAuth::redirectUri('auth/callback.php')
        );
    }

    #[Test]
    public function die_variable_schlaegt_die_herleitung(): void
    {
        $_SERVER['HTTP_HOST'] = 'fabrica-ignis-kreis-nord';
        $_ENV['DISCORD_REDIRECT_URI'] = 'https://kreis-nord.example.de/auth/callback.php';

        $this->assertSame(
            'https://kreis-nord.example.de/auth/callback.php',
            DiscordOAuth::redirectUri('auth/callback.php')
        );
    }

    /**
     * Eine leer gesetzte Variable ist keine Konfiguration. Sonst schickte eine
     * vergessene Zeile in der .env jede Anmeldung gegen eine leere Adresse.
     */
    #[Test]
    public function eine_leere_variable_zaehlt_nicht_als_gesetzt(): void
    {
        $_SERVER['HTTP_HOST'] = 'intra.example.de';
        $_SERVER['HTTPS'] = 'on';
        $_ENV['DISCORD_REDIRECT_URI'] = '   ';

        $this->assertSame(
            ProtocolDetection::buildRedirectUri('auth/callback.php'),
            DiscordOAuth::redirectUri('auth/callback.php')
        );
    }

    private function baseUrlOverHttps(): string
    {
        $_SERVER['HTTPS'] = 'on';

        return ProtocolDetection::getBaseUrl();
    }
}
