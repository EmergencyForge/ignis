<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\AntragSettingsController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AntragSettingsControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(AntragSettingsController::class);
        $this->assertInstanceOf(AntragSettingsController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(AntragSettingsController::class);
        foreach (['listAction', 'createForm', 'edit'] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "AntragSettingsController::$method() fehlt");
        }
    }
}
