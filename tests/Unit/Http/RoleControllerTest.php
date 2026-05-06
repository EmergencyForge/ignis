<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\RoleController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(RoleController::class);
        $this->assertInstanceOf(RoleController::class, $controller);
    }

    #[Test]
    public function controller_has_expected_action_methods(): void
    {
        $reflection = new \ReflectionClass(RoleController::class);
        foreach (['index', 'store', 'update', 'destroy'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "RoleController::$method() fehlt"
            );
        }
    }

    #[Test]
    public function permissions_config_is_loadable_and_grouped(): void
    {
        $groups = require dirname(__DIR__, 3) . '/config/permissions.php';
        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);
        // Sanity: bekannte Gruppen vorhanden
        $this->assertArrayHasKey('Benutzer', $groups);
        $this->assertArrayHasKey('Sonstiges', $groups);
        // Bekannte Permission vorhanden
        $this->assertArrayHasKey('users.view', $groups['Benutzer']);
        $this->assertArrayHasKey('admin', $groups['Sonstiges']);
    }
}
