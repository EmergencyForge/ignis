<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Controllers\NotificationController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(NotificationController::class);
        $this->assertInstanceOf(NotificationController::class, $controller);
    }

    #[Test]
    public function controller_has_all_action_methods(): void
    {
        $reflection = new \ReflectionClass(NotificationController::class);
        foreach (['index', 'markAsRead', 'markAllAsRead', 'delete'] as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "NotificationController::$method() fehlt"
            );
        }
    }
}
