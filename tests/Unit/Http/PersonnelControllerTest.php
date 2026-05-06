<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\PersonnelController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonnelControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(PersonnelController::class);
        $this->assertInstanceOf(PersonnelController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(PersonnelController::class);
        $methods = [
            'index', 'store', 'destroy', 'deleteComment',
            'show', 'update', 'updateFachdienste', 'addNote', 'createDocument',
            'showDocument', 'deleteDocument',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "PersonnelController::$method() fehlt"
            );
        }
    }
}
