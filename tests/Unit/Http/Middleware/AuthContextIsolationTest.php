<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-Context-Regression: jede der 5 parallelen Auth-Wege darf NUR
 * von der für sie zuständigen Middleware akzeptiert werden. Diese Tests
 * verhindern, dass eine Refactoring-Welle aus Versehen z.B. den eNOTF-
 * Session-Token als Login-Equivalent auswertet (war historisch ein Risiko).
 *
 * Die 5 Kontexte:
 *   1. Standard-User              — \$_SESSION['userid']
 *   2. eNOTF-Crew                 — \$_SESSION['enotf_session_token']
 *   3. FireTab                    — \$_SESSION['einsatz_vehicle_id']
 *   4. API-Key (FiveM Server)     — X-API-Key Header
 *   5. Federation (Server-to-Server) — X-Federation-Key Header (anderer Test)
 */
final class AuthContextIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        if (!defined('API_KEY')) {
            define('API_KEY', 'unit-test-api-key');
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function ok(): callable
    {
        return fn () => Response::text('ok');
    }

    // ─── AuthMiddleware (Standard-User) ──────────────────────────────

    #[Test]
    public function auth_middleware_lehnt_enotf_only_session_ab(): void
    {
        // eNOTF-Crew aktiv, aber KEIN Standard-User-Login
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer' => ['name' => 'X', 'quali' => 'NS'],
        ]);

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());

        $this->assertSame(401, $res->status, 'eNOTF-Token darf NICHT als User-Login zählen');
    }

    #[Test]
    public function auth_middleware_lehnt_einsatz_only_session_ab(): void
    {
        SessionManager::loginEinsatz(7, 'LF 10/1', 42, 'Op');

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());

        $this->assertSame(401, $res->status, 'FireTab-Vehicle-Login darf NICHT als User-Login zählen');
    }

    #[Test]
    public function auth_middleware_lehnt_klinikcode_only_session_ab(): void
    {
        SessionManager::loginKlinikcode('ENR-001');

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());

        $this->assertSame(401, $res->status, 'Klinikcode-Zugang darf NICHT als User-Login zählen');
    }

    #[Test]
    public function auth_middleware_lehnt_char_only_session_ab(): void
    {
        SessionManager::loginCharacter('char-1', 'fire', 'John');

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());

        $this->assertSame(401, $res->status, 'FiveM-Char-Identify darf NICHT als User-Login zählen');
    }

    #[Test]
    public function auth_middleware_akzeptiert_user_session(): void
    {
        SessionManager::loginUser(
            ['id' => 42, 'username' => 'alice', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd'],
            ['admin'],
        );

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());

        $this->assertSame(200, $res->status);
    }

    // ─── ApiKeyMiddleware ────────────────────────────────────────────

    #[Test]
    public function api_key_middleware_ignoriert_user_session(): void
    {
        // Standard-User eingeloggt, aber KEIN API-Key gesetzt → reject von Remote
        SessionManager::loginUser(
            ['id' => 42, 'username' => 'alice', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd'],
            ['admin'],
        );

        $mw  = new ApiKeyMiddleware();
        $req = new Request('POST', '/api/asu/sync', server: ['REMOTE_ADDR' => '10.0.0.5']);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(403, $res->status, 'User-Session darf API-Key-Auth NICHT umgehen');
    }

    #[Test]
    public function api_key_middleware_ignoriert_enotf_session(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer' => ['name' => 'X', 'quali' => 'NS'],
        ]);

        $mw  = new ApiKeyMiddleware();
        $req = new Request('POST', '/api/asu/sync', server: ['REMOTE_ADDR' => '10.0.0.5']);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(403, $res->status);
    }

    // ─── Stacking: User + eNOTF parallel ─────────────────────────────

    #[Test]
    public function user_und_enotf_parallel_lassen_user_routes_durch(): void
    {
        SessionManager::loginUser(
            ['id' => 1, 'username' => 'a', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd'],
            ['admin'],
        );
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer' => ['name' => 'X', 'quali' => 'NS'],
        ]);

        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/benutzer/list'), $this->ok());

        $this->assertSame(200, $res->status);
    }

    // ─── Set Cleanup: logout_user kappt nur User, nicht eNOTF ────────

    #[Test]
    public function logout_user_laesst_enotf_route_weiterlaufen(): void
    {
        SessionManager::loginUser(
            ['id' => 1, 'username' => 'a', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd'],
            ['admin'],
        );
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer' => ['name' => 'X', 'quali' => 'NS'],
        ]);

        SessionManager::logoutUser();

        // User-Auth kaputt
        $mw  = new AuthMiddleware();
        $res = $mw->process(new Request('GET', '/api/users/list'), $this->ok());
        $this->assertSame(401, $res->status);

        // eNOTF-Token noch da
        $this->assertTrue(SessionManager::isEnotfActive());
    }

    // ─── Redirect-Behavior ───────────────────────────────────────────

    #[Test]
    public function html_redirect_speichert_request_uri_nicht_query_only(): void
    {
        $mw  = new AuthMiddleware();
        $req = new Request('GET', '/einsatz/view', server: [
            'REQUEST_URI' => '/einsatz/view?id=42&tab=lage',
        ]);

        $res = $mw->process($req, $this->ok());

        $this->assertSame(302, $res->status);
        $this->assertSame('/einsatz/view?id=42&tab=lage', $_SESSION['redirect_url']);
    }
}
