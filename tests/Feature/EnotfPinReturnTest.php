<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use EmergencyForge\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Helpers\EnotfUrl;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Nach der PIN-Sperre geht es auf die Seite zurück, auf der gesperrt wurde:
 * ob pin_activity.js sie als ?return mitschickt oder die Middleware sie beim
 * nächsten Request merkt. Fremde Ziele landen auf der Übersicht.
 */
final class EnotfPinReturnTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!CrewPolicy::pinEnabled() || !defined('ENOTF_PIN')) {
            $this->markTestSkipped('In dieser Datenbank ist die eNOTF-PIN aus.');
        }
        // Ohne edivi.view, sonst überspringt die Middleware den Lockscreen
        $this->actingAs(FixtureFactory::user()->id, ['permissions' => []]);
    }

    private function pinEingeben(): Response
    {
        $response = $this->post('/enotf/lockscreen', ['_csrf' => 'x', 'pin' => (string) ENOTF_PIN]);
        $this->assertStatus(302, $response);

        return $response;
    }

    #[Test]
    public function fuehrt_auf_die_seite_zurueck_die_pin_activity_mitschickt(): void
    {
        $seite = EnotfUrl::protokoll('123', 'anamnese') . '?t=anamnese';

        $this->assertOk($this->get('/enotf/lockscreen', ['query' => ['return' => $seite]]));

        $this->assertSame($seite, $this->pinEingeben()->headers['Location'] ?? null);
    }

    #[Test]
    public function fuehrt_auf_die_seite_zurueck_die_die_middleware_gesperrt_hat(): void
    {
        $seite = EnotfUrl::protokoll('123', 'erstbefund') . '?t=atemwege&q=2';
        $_SESSION['pin_verified']      = true;
        $_SESSION['pin_last_activity'] = time() - SessionManager::pinTimeout() - 1;

        $gesperrt = $this->get('/enotf/p/123/erstbefund', [
            'query'  => ['t' => 'atemwege', 'q' => '2'],
            'server' => ['REQUEST_URI' => $seite],
        ]);
        $this->assertRedirect($gesperrt, EnotfUrl::page('lockscreen'));

        $this->assertSame($seite, $this->pinEingeben()->headers['Location'] ?? null);
    }

    /** @return array<string, array{string}> */
    public static function fremdeZiele(): array
    {
        return [
            'absolute URL'           => ['https://evil.example/enotf/overview'],
            'ohne Schema'            => ['//evil.example/enotf/overview'],
            'Backslash'              => ['/\\evil.example/enotf/overview'],
            'anderer Bereich'        => ['/admin/users'],
            'aus dem eNOTF hinaus'   => ['/enotf/../admin/users'],
            'kodiert hinaus'         => ['/enotf/%2E%2E/admin/users'],
            'Sperrbildschirm selbst' => ['/enotf/lockscreen?return=/enotf/overview'],
            'Zeilenumbruch'          => ["/enotf/overview\r\nX-Test: 1"],
        ];
    }

    #[Test]
    #[DataProvider('fremdeZiele')]
    public function fremdes_ziel_fuehrt_zur_uebersicht(string $ziel): void
    {
        $this->get('/enotf/lockscreen', ['query' => ['return' => $ziel]]);

        $this->assertSame(EnotfUrl::page('overview'), $this->pinEingeben()->headers['Location'] ?? null);
    }

    #[Test]
    public function auch_ein_gespeichertes_fremdes_ziel_fuehrt_zur_uebersicht(): void
    {
        SessionManager::setPinReturnUrl('https://evil.example/');

        $this->assertSame(EnotfUrl::page('overview'), $this->pinEingeben()->headers['Location'] ?? null);
    }

    #[Test]
    public function ohne_ziel_bleibt_es_bei_der_uebersicht(): void
    {
        $this->assertSame(EnotfUrl::page('overview'), $this->pinEingeben()->headers['Location'] ?? null);
    }
}
