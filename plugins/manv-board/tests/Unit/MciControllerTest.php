<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Tests\Unit;

use Plugin\ManvBoard\Controllers\MciController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MciControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(MciController::class);
        $this->assertInstanceOf(MciController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(MciController::class);
        $methods = [
            'index', 'create', 'store', 'edit', 'update', 'log',
            'board', 'patientCreate', 'patientStore', 'patientView', 'patientUpdate',
            'ressourcen', 'ressourceStore', 'ressourceUpdate', 'ressourceDelete',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "MciController::$method() fehlt"
            );
        }
    }
}
