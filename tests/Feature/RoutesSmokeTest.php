<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Hartes Smoke-Test-Set für die Route-Pipeline.
 *
 * Hintergrund: Beim flächendeckenden `.php`-Suffix-Dedup hatte das
 * Bulk-Skript bei sechs Multi-Line-Routen in routes/api.php nur die
 * erste Zeile entfernt; die orphan Continuation-Args blieben stehen
 * und führten zu `unexpected token ","` in jedem API-Request. Die
 * Existenz dieses Tests sorgt dafür, dass künftige Bulk-Migrationen
 * solche Schäden in CI sofort sichtbar machen — `loadRoutes()` ruft
 * `require` auf jede Route-Datei auf, wodurch Parse-Fehler die Test-
 * Suite scheitern lassen, BEVOR ein einzelner Endpoint angefasst wird.
 */
final class RoutesSmokeTest extends FeatureTestCase
{
    #[Test]
    public function alle_route_dateien_parsen_und_laden_ohne_fehler(): void
    {
        // Wenn setUp() durchläuft, hat loadRoutes() routes/web.php +
        // routes/api.php + routes/api.session.php erfolgreich required.
        // Allein die Existenz des Routers in setUp() ist die Assertion.
        $this->assertNotNull($this->router);
    }

    #[Test]
    public function api_smoke_test_route_pingt_erfolgreich(): void
    {
        $response = $this->get('/api/_router/ping');

        $this->assertOk($response);
        $body = $this->assertJsonResponse($response);
        $this->assertSame('pong',  $body['message'] ?? null);
        $this->assertSame('api',   $body['scope']   ?? null);
    }

    #[Test]
    public function web_smoke_test_route_pingt_erfolgreich(): void
    {
        $response = $this->get('/_router/ping');

        $this->assertOk($response);
        $body = $this->assertJsonResponse($response);
        $this->assertSame('pong', $body['message'] ?? null);
    }

    #[Test]
    public function ungeschuetzter_admin_endpoint_redirectet_zum_login(): void
    {
        // Mehrere Auth-protected Routen kurz anpicken — wenn eine 200
        // statt 302 liefert, ist die AuthMiddleware nicht mehr eingehängt.
        $response = $this->get('/benutzer/list');
        $this->assertSame(302, $response->status, 'Erwartet Redirect auf Login, bekommen ' . $response->status);
    }

    #[Test]
    public function api_endpoint_ohne_session_liefert_401(): void
    {
        $response = $this->get('/api/notifications/poll');
        $this->assertUnauthorized($response);
    }
}
