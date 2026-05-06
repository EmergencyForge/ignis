<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Settings;

use App\Http\Controllers\Settings\PersonalController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonalControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(PersonalController::class);
        $this->assertInstanceOf(PersonalController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(PersonalController::class);
        $methods = [
            'dienstgradeIndex', 'dienstgradStore', 'dienstgradUpdate', 'dienstgradDelete',
            'fwQualiIndex', 'fwQualiStore', 'fwQualiUpdate', 'fwQualiDelete',
            'rdQualiIndex', 'rdQualiStore', 'rdQualiUpdate', 'rdQualiDelete',
            'fdQualiIndex', 'fdQualiStore', 'fdQualiUpdate', 'fdQualiDelete',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "PersonalController::$method() fehlt"
            );
        }
    }
}
