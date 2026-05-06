<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Exceptions\AuthorizationException;
use App\Http\Middleware\PolicyMiddleware;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean session state — policies lesen oft $_SESSION['userid']/['permissions']
        $_SESSION['userid']       = 1;
        $_SESSION['permissions']  = ['full_admin'];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['userid'], $_SESSION['permissions']);
        parent::tearDown();
    }

    private function ok(): callable
    {
        return fn () => Response::text('ok');
    }

    #[Test]
    public function allows_request_when_ability_is_granted(): void
    {
        // full_admin darf alles — UserPolicy::viewList() prüft Permissions::check(['admin', 'users.view'])
        $mw = new PolicyMiddleware('user.viewList');
        $res = $mw->process(new Request('GET', '/users'), $this->ok());

        $this->assertSame(200, $res->status);
        $this->assertSame('ok', $res->body);
    }

    #[Test]
    public function returns_403_json_for_api_request_when_denied(): void
    {
        // Session ohne permissions → Policy liefert meist false
        $_SESSION['permissions'] = [];

        $mw  = new PolicyMiddleware('user.update', resourceParam: 'id');
        $req = new Request('POST', '/api/users/5');
        $req = $req->withAttribute('id', '5');

        $res = $mw->process($req, $this->ok());

        $this->assertSame(403, $res->status);
        $this->assertStringContainsString('Keine Berechtigung', $res->body);
        $this->assertStringContainsString('user.update', $res->body);
    }

    #[Test]
    public function returns_redirect_for_html_request_when_denied(): void
    {
        $_SESSION['permissions'] = [];

        $mw  = new PolicyMiddleware('user.delete', resourceParam: 'id');
        $req = new Request('GET', '/users/7/delete', server: ['REQUEST_URI' => '/users/7/delete']);
        $req = $req->withAttribute('id', '7');

        $res = $mw->process($req, $this->ok());

        $this->assertSame(302, $res->status);
        // Middleware redirected zur Index-Seite (clean URL, ohne `.php`-Suffix —
        // siehe Front-Controller-Stripping in public/index.php).
        $this->assertStringContainsString('/index', $res->headers['Location'] ?? '');
    }

    #[Test]
    public function resolves_resource_from_request_attribute(): void
    {
        // Policy ohne bekannte Ability — `Gate::allows` gibt false zurück bei
        // nicht existierenden Klassen/Methoden. Wir verifizieren nur, dass
        // die Middleware nicht crasht beim Resource-Resolve.
        $mw  = new PolicyMiddleware('nonexistent.doSomething', resourceParam: 'id');
        $req = (new Request('GET', '/api/foo'))->withAttribute('id', '42');

        $res = $mw->process($req, $this->ok());
        $this->assertSame(403, $res->status);
    }

    #[Test]
    public function treats_null_resource_for_class_level_abilities(): void
    {
        // Ohne resourceParam → Policy-Methode bekommt null
        // UserPolicy::viewAuditLog nimmt kein Target, full_admin erlaubt es
        $mw = new PolicyMiddleware('user.viewAuditLog');
        $res = $mw->process(new Request('GET', '/admin/audit'), $this->ok());

        $this->assertSame(200, $res->status);
    }
}
