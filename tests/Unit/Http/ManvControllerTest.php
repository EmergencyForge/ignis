<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\ManvController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManvControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(ManvController::class);
        $this->assertInstanceOf(ManvController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(ManvController::class);
        $methods = [
            'index', 'create', 'store', 'edit', 'update', 'log',
            'board', 'patientCreate', 'patientStore', 'patientView', 'patientUpdate',
            'ressourcen', 'ressourceStore', 'ressourceUpdate', 'ressourceDelete',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "ManvController::$method() fehlt"
            );
        }
    }
}
