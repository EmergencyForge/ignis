<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\LogsController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogsControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(LogsController::class);
        $this->assertInstanceOf(LogsController::class, $controller);
    }

    #[Test]
    public function controller_has_index_method(): void
    {
        $reflection = new \ReflectionClass(LogsController::class);
        $this->assertTrue($reflection->hasMethod('index'));
    }
}
