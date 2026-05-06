<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\EnotfPrintController;
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
