<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\AntragController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AntragControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(AntragController::class);
        $this->assertInstanceOf(AntragController::class, $controller);
    }

    #[Test]
    public function controller_has_expected_action_methods(): void
    {
        $reflection = new \ReflectionClass(AntragController::class);
        foreach (['selectType', 'create', 'store', 'view', 'adminList', 'adminView', 'decide'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "AntragController::$method() fehlt"
            );
        }
    }
}
