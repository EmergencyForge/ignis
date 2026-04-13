<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Session-Zustand für jeden Test zurücksetzen
        unset($_SESSION['userid']);
    }

    private function ok(): callable
    {
        return fn () => Response::text('ok');
    }

    #[Test]
    public function allows_request_when_user_is_logged_in(): void
    {
        $_SESSION['userid'] = 42;
        $mw = new AuthMiddleware();

        $res = $mw->process(new Request('GET', '/dashboard'), $this->ok());

        $this->assertSame(200, $res->status);
        $this->assertSame('ok', $res->body);
    }

    #[Test]
    public function redirects_to_login_for_html_request_when_not_logged_in(): void
    {
        $mw = new AuthMiddleware();
        $req = new Request('GET', '/benutzer/list', server: ['REQUEST_URI' => '/benutzer/list']);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(302, $res->status);
        $this->assertStringContainsString('login.php', $res->headers['Location'] ?? '');
        $this->assertSame('/benutzer/list', $_SESSION['redirect_url'] ?? null);
    }

    #[Test]
    public function returns_401_json_for_api_request_when_not_logged_in(): void
    {
        $mw  = new AuthMiddleware();
        $req = new Request('GET', '/api/users/list');

        $res = $mw->process($req, $this->ok());

        $this->assertSame(401, $res->status);
        $this->assertStringContainsString('Nicht authentifiziert', $res->body);
    }

    #[Test]
    public function config_flag_gates_activation(): void
    {
        // Flag NICHT definiert → Middleware ist inaktiv, Request geht durch
        $mw = new AuthMiddleware('TEST_FLAG_DOES_NOT_EXIST');

        $res = $mw->process(new Request('GET', '/enotf/irgendwas'), $this->ok());

        $this->assertSame(200, $res->status);
        $this->assertSame('ok', $res->body);
    }

    #[Test]
    public function config_flag_enforces_auth_when_flag_is_true(): void
    {
        // Runtime-Konstante definieren und Middleware mit diesem Flag-Namen bauen
        if (!defined('AUTH_TEST_ENFORCE')) {
            define('AUTH_TEST_ENFORCE', true);
        }
        $mw = new AuthMiddleware('AUTH_TEST_ENFORCE');

        $res = $mw->process(new Request('GET', '/api/foo'), $this->ok());

        // Flag=true → Auth wird gefordert, ohne Session = 401
        $this->assertSame(401, $res->status);
    }

    #[Test]
    public function inverted_flag_treats_true_as_public(): void
    {
        if (!defined('AUTH_TEST_PUBLIC')) {
            define('AUTH_TEST_PUBLIC', true);
        }
        // invert=true → "Auth erforderlich AUSSER Flag ist true"
        // Flag=true → Middleware inaktiv, Request geht durch obwohl keine Session
        $mw = new AuthMiddleware('AUTH_TEST_PUBLIC', invert: true);

        $res = $mw->process(new Request('GET', '/wissensdb/foo'), $this->ok());

        $this->assertSame(200, $res->status);
    }
}
