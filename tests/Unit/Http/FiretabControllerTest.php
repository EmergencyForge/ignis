<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\FiretabController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiretabControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(FiretabController::class);
        $this->assertInstanceOf(FiretabController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(FiretabController::class);
        $methods = [
            'index', 'loginForm', 'login', 'list',
            'view', 'createForm', 'store', 'dispatchAction',
            'statusmeldungen', 'asuForm', 'fireTabFahrtenbuch', 'adminList',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "FiretabController::$method() fehlt"
            );
        }
    }
}
