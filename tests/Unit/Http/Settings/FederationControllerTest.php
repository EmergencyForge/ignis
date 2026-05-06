<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\FederationController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FederationControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(FederationController::class);
        $this->assertInstanceOf(FederationController::class, $controller);
    }

    #[Test]
    public function controller_has_index_method(): void
    {
        $reflection = new \ReflectionClass(FederationController::class);
        $this->assertTrue($reflection->hasMethod('index'));
    }
}
