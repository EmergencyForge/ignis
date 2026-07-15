<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Controllers\EnotfSchnittstelleController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfSchnittstelleControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EnotfSchnittstelleController::class);
        $this->assertInstanceOf(EnotfSchnittstelleController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(EnotfSchnittstelleController::class);
        $methods = ['index', 'klinikcode', 'voranmeldung', 'hospitalAvailability', 'apiPrereg'];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "EnotfSchnittstelleController::$method() fehlt");
        }
    }
}
