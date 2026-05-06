<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\EnotfAdminController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfAdminControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EnotfAdminController::class);
        $this->assertInstanceOf(EnotfAdminController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(EnotfAdminController::class);
        $methods = ['listAction', 'destroy', 'qmActionsModal', 'qmLogModal', 'bulkDeleteEmpty'];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "EnotfAdminController::$method() fehlt");
        }
    }
}
