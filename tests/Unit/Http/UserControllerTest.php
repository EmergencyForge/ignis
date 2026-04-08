<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\UserController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity-Tests für den UserController. Echte HTTP-Action-Tests (mit Sessions
 * und Redirects) brauchen Browser-/Feature-Test-Setup, das kommt mit dem
 * Router in Phase 3. Hier nur: Klasse ist autoload-bar und vom Container
 * auflösbar.
 */
class UserControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(UserController::class);
        $this->assertInstanceOf(UserController::class, $controller);
    }

    #[Test]
    public function controller_has_expected_action_methods(): void
    {
        $reflection = new \ReflectionClass(UserController::class);
        foreach (['index', 'edit', 'update', 'destroy', 'setActive', 'auditlog', 'registrationCodes'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "UserController::$method() fehlt"
            );
        }
    }
}
