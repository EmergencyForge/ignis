<?php

declare(strict_types=1);

namespace Plugin\Logbook\Tests\Unit;

use Plugin\Logbook\Controllers\LogbookController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogbookControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(LogbookController::class);
        $this->assertInstanceOf(LogbookController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(LogbookController::class);
        foreach (['index', 'store', 'update', 'destroy'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "LogbookController::$method() fehlt"
            );
        }
    }
}
