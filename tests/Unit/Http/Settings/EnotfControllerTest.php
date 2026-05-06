<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\EnotfController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EnotfController::class);
        $this->assertInstanceOf(EnotfController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(EnotfController::class);
        $methods = [
            'index', 'store', 'update', 'destroy',
            'categoriesIndex', 'categoryStore', 'categoryUpdate', 'categoryDestroy',
        ];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "EnotfController::$method() fehlt");
        }
    }
}
