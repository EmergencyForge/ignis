<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiKeyMiddleware;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('API_KEY')) {
            define('API_KEY', 'test-secret-key-1234');
        }
    }

    private function ok(): callable
    {
        return fn ($req) => Response::json(['ok' => true, 'source' => $req->attribute('api_auth')]);
    }

    /**
     * Führt $fn mit einem bestimmten APP_ENV aus; null heißt „nirgends gesetzt".
     *
     * Muss alle Quellen anfassen, nicht nur $_ENV. Die Middleware liest
     * `$_ENV['APP_ENV'] ?? getenv('APP_ENV')`, und docker-compose.yml setzt
     * APP_ENV=development in die Prozessumgebung. Ein blosses
     * unset($_ENV['APP_ENV']) laesst getenv() also unberuehrt — auf dem Host
     * gruen, im Container rot, weil dort der Localhost-Bypass weiter griff.
     */
    private function withAppEnv(?string $value, callable $fn): void
    {
        $vorherEnv    = $_ENV['APP_ENV']    ?? null;
        $vorherServer = $_SERVER['APP_ENV'] ?? null;
        $vorherGetenv = getenv('APP_ENV');

        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        putenv('APP_ENV');

        if ($value !== null) {
            $_ENV['APP_ENV'] = $value;
            putenv('APP_ENV=' . $value);
        }

        try {
            $fn();
        } finally {
            unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
            putenv('APP_ENV');

            if ($vorherEnv !== null) {
                $_ENV['APP_ENV'] = $vorherEnv;
            }
            if ($vorherServer !== null) {
                $_SERVER['APP_ENV'] = $vorherServer;
            }
            if ($vorherGetenv !== false) {
                putenv('APP_ENV=' . $vorherGetenv);
            }
        }
    }

    #[Test]
    public function allows_request_from_localhost_without_key_in_development(): void
    {
        $this->withAppEnv('development', function (): void {
            $mw  = new ApiKeyMiddleware();
            $req = new Request('POST', '/api/fivem/foo', server: ['REMOTE_ADDR' => '127.0.0.1']);

            $res = $mw->process($req, $this->ok());

            $this->assertSame(200, $res->status);
            $this->assertStringContainsString('"source":"localhost"', $res->body);
        });
    }

    #[Test]
    public function rejects_request_from_localhost_without_key_in_production(): void
    {
        // Ohne APP_ENV=development verlangt die Middleware den API-Key
        // auch fuer 127.0.0.1 — Schutz vor Shared-Hosting-Nachbarn,
        // kompromittierten lokalen Scripts und gespoofter REMOTE_ADDR.
        $this->withAppEnv(null, function (): void {
            $mw  = new ApiKeyMiddleware();
            $req = new Request('POST', '/api/fivem/foo', server: ['REMOTE_ADDR' => '127.0.0.1']);

            $res = $mw->process($req, $this->ok());

            $this->assertSame(403, $res->status);
        });
    }

    #[Test]
    public function rejects_request_without_key_from_remote(): void
    {
        $mw  = new ApiKeyMiddleware();
        $req = new Request('POST', '/api/fivem/foo', server: ['REMOTE_ADDR' => '10.0.0.5']);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(403, $res->status);
    }

    #[Test]
    public function accepts_key_via_x_api_key_header(): void
    {
        $mw  = new ApiKeyMiddleware();
        $req = new Request('POST', '/api/fivem/foo', server: [
            'REMOTE_ADDR'     => '10.0.0.5',
            'HTTP_X_API_KEY'  => 'test-secret-key-1234',
        ]);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('"source":"key"', $res->body);
    }

    #[Test]
    public function accepts_key_via_query_param(): void
    {
        $mw  = new ApiKeyMiddleware();
        $req = new Request('GET', '/api/fivem/foo',
            query:  ['api_key' => 'test-secret-key-1234'],
            server: ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $res = $mw->process($req, $this->ok());

        $this->assertSame(200, $res->status);
    }

    #[Test]
    public function rejects_invalid_key(): void
    {
        $mw  = new ApiKeyMiddleware();
        $req = new Request('POST', '/api/fivem/foo', server: [
            'REMOTE_ADDR'    => '10.0.0.5',
            'HTTP_X_API_KEY' => 'wrong-key',
        ]);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(403, $res->status);
    }
}
