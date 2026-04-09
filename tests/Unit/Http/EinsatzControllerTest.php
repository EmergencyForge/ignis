<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\EinsatzController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EinsatzControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EinsatzController::class);
        $this->assertInstanceOf(EinsatzController::class, $controller);
    }

    #[Test]
    public function controller_has_turn1_methods(): void
    {
        $reflection = new \ReflectionClass(EinsatzController::class);
        // Turn 1 methods: index, loginForm, login, list
        // Turn 2/3 (board, view, create, store, actions, ...) folgen später
        foreach (['index', 'loginForm', 'login', 'list'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "EinsatzController::$method() fehlt"
            );
        }
    }
}
