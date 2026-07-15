<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Controllers\EnotfPrintController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfPrintControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EnotfPrintController::class);
        $this->assertInstanceOf(EnotfPrintController::class, $controller);
    }

    #[Test]
    public function controller_has_show_method(): void
    {
        $reflection = new \ReflectionClass(EnotfPrintController::class);
        $this->assertTrue($reflection->hasMethod('show'));
    }
}
