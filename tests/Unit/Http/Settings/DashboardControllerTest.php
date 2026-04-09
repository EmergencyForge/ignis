<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\DashboardController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(DashboardController::class);
        $this->assertInstanceOf(DashboardController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(DashboardController::class);
        $methods = [
            'index',
            'categoryStore', 'categoryUpdate', 'categoryDestroy',
            'tileStore', 'tileUpdate', 'tileDestroy',
        ];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "DashboardController::$method() fehlt");
        }
    }
}
