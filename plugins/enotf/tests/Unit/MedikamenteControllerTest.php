<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Controllers\Settings\MedikamenteController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MedikamenteControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(MedikamenteController::class);
        $this->assertInstanceOf(MedikamenteController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(MedikamenteController::class);
        foreach (['index', 'store', 'update', 'destroy'] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "MedikamenteController::$method() fehlt");
        }
    }
}
