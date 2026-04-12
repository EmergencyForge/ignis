<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\EnotfProtokollController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnotfProtokollControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EnotfProtokollController::class);
        $this->assertInstanceOf(EnotfProtokollController::class, $controller);
    }

    #[Test]
    public function controller_has_serve_method(): void
    {
        $reflection = new \ReflectionClass(EnotfProtokollController::class);
        $this->assertTrue($reflection->hasMethod('serve'));
    }
}
