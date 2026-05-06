<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\FormsController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormsControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(FormsController::class);
        $this->assertInstanceOf(FormsController::class, $controller);
    }

    #[Test]
    public function controller_has_expected_action_methods(): void
    {
        $reflection = new \ReflectionClass(FormsController::class);
        foreach (['selectType', 'create', 'store', 'view', 'adminList', 'adminView', 'decide'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "FormsController::$method() fehlt"
            );
        }
    }
}
