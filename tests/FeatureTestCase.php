<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

/**
 * Base für Feature-Tests, die den echten Router + Middleware-Pipeline
 * + Controller-Stack durchlaufen.
 *
 * Unterschied zu IntegrationTestCase:
 *   - IntegrationTestCase: testet einzelne Klassen/Models gegen die Test-DB
 *   - FeatureTestCase: testet HTTP-Endpoints End-to-End (inkl. Auth,
 *     CSRF, Routing, Response-Format) gegen die Test-DB
 *
 * Pro Test wird ein frischer Router mit `enableCache: false` gebaut, und
 * die echten Route-Dateien (routes/web.php, routes/api.php, routes/api.session.php)
 * werden geladen — so testen wir dieselbe Route-Konfiguration wie in Produktion.
 *
 * Beispiel:
 *
 *   class LoginTest extends FeatureTestCase
 *   {
 *       #[Test]
 *       public function login_page_is_public(): void
 *       {
 *           $response = $this->get('/login.php');
 *           $this->assertOk($response);
 *           $this->assertBodyContains('<form', $response);
 *       }
 *
 *       #[Test]
 *       public function profile_redirects_when_not_authenticated(): void
 *       {
 *           $response = $this->get('/mitarbeiter/profile.php');
 *           $this->assertRedirect($response, '/login.php');
 *       }
 *   }
 */
abstract class FeatureTestCase extends IntegrationTestCase
{
    protected Router $router;

    /** @var array<string,mixed> Snapshot der $_SESSION vor dem Test */
    private array $sessionBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Session isolieren: alte $_SESSION wegspeichern, mit leerem Array überschreiben.
        // In tearDown wird der Original-Zustand wiederhergestellt, damit der PHPUnit-Runner
        // selbst keine durchgestreuten Session-Werte sieht.
        $this->sessionBefore = $_SESSION ?? [];
        $_SESSION = [];

        // Frischer Router pro Test — kein File-Cache, sodass Test-Routen-
        // Änderungen sofort greifen und keine Live-Cache-Files die Tests
        // verfälschen.
        $this->router = new Router(
            $this->container,
            $this->container->get(Pipeline::class),
            enableCache: false,
        );

        $this->loadRoutes();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBefore;
        parent::tearDown();
    }

    /**
     * Lädt die Produktions-Route-Dateien gegen den Test-Router.
     * Overridable für Tests, die nur bestimmte Route-Dateien brauchen.
     */
    protected function loadRoutes(): void
    {
        $router = $this->router;  // phpcs:ignore — wird von den require'd Route-Files benutzt
        $routeFiles = [
            dirname(__DIR__) . '/routes/web.php',
            dirname(__DIR__) . '/routes/api.php',
            dirname(__DIR__) . '/routes/api.session.php',
        ];
        foreach ($routeFiles as $file) {
            if (is_file($file)) {
                require $file;
            }
        }
    }

    // ── Request-Helper ────────────────────────────────────────────────

    /**
     * Simuliert einen HTTP-Request und liefert die Response.
     *
     * @param array{
     *   query?: array<string,mixed>,
     *   post?: array<string,mixed>,
     *   headers?: array<string,string>,
     *   server?: array<string,string>,
     *   cookies?: array<string,string>,
     *   files?: array<string,mixed>,
     *   session?: array<string,mixed>,
     * } $opts
     */
    protected function request(string $method, string $path, array $opts = []): Response
    {
        // Session-Injections vor Dispatch setzen. Pro Test kumulativ —
        // actingAs() + zusätzliche session-Values funktionieren beide.
        foreach ($opts['session'] ?? [] as $k => $v) {
            $_SESSION[$k] = $v;
        }

        // Headers → $_SERVER mapping (HTTP_*), damit Request::header() sie findet
        $server = $opts['server'] ?? [];
        foreach ($opts['headers'] ?? [] as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = $value;
        }

        $request = new Request(
            method:  strtoupper($method),
            path:    $path,
            query:   $opts['query'] ?? [],
            post:    $opts['post'] ?? [],
            server:  $server,
            cookies: $opts['cookies'] ?? [],
            files:   $opts['files'] ?? [],
        );

        // Output-Buffer einschalten — Legacy-Controller rufen teilweise
        // `echo`/`include` direkt und setzen dann `emitted=true`. Für Tests
        // wollen wir den Body im Response haben, also fangen wir's ab.
        ob_start();
        try {
            $response = $this->router->dispatch($request);
        } finally {
            $captured = ob_get_clean() ?: '';
        }

        // Wenn Controller direkt ausgegeben hat (emitted=true + leerer body),
        // merken wir das Captured-Output in einem neuen Response-Objekt, damit
        // Assertions auf `body` weiterhin funktionieren.
        if ($response->emitted && $response->body === '' && $captured !== '') {
            $response = new Response(
                status:  $response->status,
                body:    $captured,
                headers: $response->headers,
                emitted: false,
            );
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $opts
     */
    protected function get(string $path, array $opts = []): Response
    {
        return $this->request('GET', $path, $opts);
    }

    /**
     * @param array<string,mixed> $body  POST-Daten (werden mit $opts['post'] gemerged)
     * @param array<string,mixed> $opts
     */
    protected function post(string $path, array $body = [], array $opts = []): Response
    {
        $opts['post'] = array_merge($opts['post'] ?? [], $body);
        return $this->request('POST', $path, $opts);
    }

    // ── Auth-Helper ───────────────────────────────────────────────────

    /**
     * Markiert den Test als "eingeloggt als User X". Session-Keys die die
     * existierenden Middlewares/Controller lesen werden gesetzt.
     */
    protected function actingAs(int $userId, array $extraSession = []): self
    {
        $_SESSION['userid']   = $userId;
        $_SESSION['logindyn'] = 1;  // Flag das in einigen Legacy-Checks abgefragt wird
        foreach ($extraSession as $k => $v) {
            $_SESSION[$k] = $v;
        }
        return $this;
    }

    // ── Assertions ────────────────────────────────────────────────────

    protected function assertStatus(int $expected, Response $response): void
    {
        $this->assertSame(
            $expected,
            $response->status,
            "Expected HTTP $expected, got {$response->status}:\n" . substr($response->body, 0, 500),
        );
    }

    protected function assertOk(Response $response): void
    {
        $this->assertStatus(200, $response);
    }

    protected function assertRedirect(Response $response, ?string $toPath = null): void
    {
        $this->assertContains(
            $response->status,
            [301, 302, 303, 307, 308],
            "Expected redirect status, got {$response->status}",
        );
        if ($toPath !== null) {
            $location = $response->headers['Location'] ?? '';
            $this->assertStringContainsString(
                $toPath,
                $location,
                "Expected redirect Location to contain '$toPath', got '$location'",
            );
        }
    }

    protected function assertNotFound(Response $response): void
    {
        $this->assertStatus(404, $response);
    }

    protected function assertUnauthorized(Response $response): void
    {
        $this->assertStatus(401, $response);
    }

    protected function assertForbidden(Response $response): void
    {
        $this->assertStatus(403, $response);
    }

    protected function assertBodyContains(string $needle, Response $response): void
    {
        $this->assertStringContainsString(
            $needle,
            $response->body,
            "Response body does not contain '$needle'",
        );
    }

    protected function assertBodyNotContains(string $needle, Response $response): void
    {
        $this->assertStringNotContainsString(
            $needle,
            $response->body,
            "Response body unexpectedly contains '$needle'",
        );
    }

    /**
     * Prüft dass die Response JSON ist und liefert den dekodierten Body.
     * Heißt bewusst nicht `assertJson` — die Methode ist in PHPUnit 10
     * final, deshalb hier ein eigener Name.
     *
     * @return array<array-key,mixed>
     */
    protected function assertJsonResponse(Response $response): array
    {
        $ct = $response->headers['Content-Type'] ?? '';
        $this->assertStringContainsString(
            'application/json',
            $ct,
            "Expected JSON Content-Type, got '$ct'",
        );
        $decoded = json_decode($response->body, true);
        $this->assertIsArray($decoded, 'Response body is not valid JSON: ' . substr($response->body, 0, 200));
        return $decoded;
    }
}
