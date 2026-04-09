<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\SystemController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(SystemController::class);
        $this->assertInstanceOf(SystemController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(SystemController::class);
        foreach (['index', 'config', 'performance', 'telemetry', 'regenerateApiKey'] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "SystemController::$method() fehlt");
        }
    }
}
