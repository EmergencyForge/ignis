<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\MitarbeiterController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MitarbeiterControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(MitarbeiterController::class);
        $this->assertInstanceOf(MitarbeiterController::class, $controller);
    }

    #[Test]
    public function controller_has_turn1_methods(): void
    {
        $reflection = new \ReflectionClass(MitarbeiterController::class);
        foreach (['index', 'store', 'destroy', 'deleteComment'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "MitarbeiterController::$method() fehlt"
            );
        }
    }
}
