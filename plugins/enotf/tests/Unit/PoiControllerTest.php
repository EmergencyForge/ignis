<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Controllers\Settings\PoiController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PoiControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(PoiController::class);
        $this->assertInstanceOf(PoiController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(PoiController::class);
        $methods = [
            'index', 'store', 'update', 'destroy',
            'departmentsIndex', 'departmentStore', 'departmentUpdate', 'departmentDestroy',
            'departmentResetAvailability', 'accessCodes',
        ];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "PoiController::$method() fehlt");
        }
    }
}
