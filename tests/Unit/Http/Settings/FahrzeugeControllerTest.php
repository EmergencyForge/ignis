<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\FahrzeugeController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FahrzeugeControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(FahrzeugeController::class);
        $this->assertInstanceOf(FahrzeugeController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(FahrzeugeController::class);
        $methods = [
            'index', 'store', 'update', 'destroy',
            'beladelistenIndex', 'beladungHandler',
            'defekteIndex',
        ];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "FahrzeugeController::$method() fehlt");
        }
    }
}
