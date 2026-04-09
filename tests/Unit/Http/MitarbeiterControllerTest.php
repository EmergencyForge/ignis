<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\MitarbeiterController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MitarbeiterControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(MitarbeiterController::class);
        $this->assertInstanceOf(MitarbeiterController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(MitarbeiterController::class);
        $methods = [
            'index', 'store', 'destroy', 'deleteComment',                       // Turn 1
            'show', 'update', 'updateFachdienste', 'addNote', 'createDocument', // Turn 2
            'showDocument', 'deleteDocument',                                   // Turn 3
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "MitarbeiterController::$method() fehlt"
            );
        }
    }
}
