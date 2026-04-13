<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\NotificationController;
use App\Http\Request;
use App\Http\Validation\ValidationException;
use App\Notifications\NotificationManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke-Tests für NotificationController.
 *
 * Der Controller delegiert an NotificationManager (der seinerseits direkt
 * gegen PDO arbeitet). Wir mocken den Manager mit einer anonymen Klasse
 * und übergeben ihn dem Controller per Constructor-Injection — das
 * vermeidet DB-Abhängigkeit in Unit-Tests.
 */
class NotificationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION['userid'] = 42;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['userid']);
        parent::tearDown();
    }

    private function makeController(NotificationManager $manager): NotificationController
    {
        return new NotificationController($manager);
    }

    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(NotificationController::class);
        $this->assertInstanceOf(NotificationController::class, $controller);
    }

    #[Test]
    public function poll_returns_unread_count_and_new_notifications(): void
    {
        $manager = new class extends NotificationManager {
            public array $lastCall = [];
            public function __construct() {}
            public function getNewSince(int $userId, string $since): array
            {
                $this->lastCall = ['user_id' => $userId, 'since' => $since];
                return [
                    'unreadCount' => 3,
                    'new'         => [
                        ['id' => 1, 'title' => 'Test 1'],
                        ['id' => 2, 'title' => 'Test 2'],
                    ],
                ];
            }
        };

        $controller = $this->makeController($manager);
        $req = new Request('GET', '/api/notifications/poll', query: ['since' => '2026-04-12 12:00:00']);

        $res = $controller->poll($req);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('"success":true', $res->body);
        $this->assertStringContainsString('"unreadCount":3', $res->body);
        $this->assertStringContainsString('"Test 1"', $res->body);
        $this->assertSame(42, $manager->lastCall['user_id']);
        $this->assertSame('2026-04-12 12:00:00', $manager->lastCall['since']);
    }

    #[Test]
    public function poll_uses_default_since_when_query_missing(): void
    {
        $manager = new class extends NotificationManager {
            public string $receivedSince = '';
            public function __construct() {}
            public function getNewSince(int $userId, string $since): array
            {
                $this->receivedSince = $since;
                return ['unreadCount' => 0, 'new' => []];
            }
        };

        $controller = $this->makeController($manager);
        $res = $controller->poll(new Request('GET', '/api/notifications/poll'));

        $this->assertSame(200, $res->status);
        // Default ist "-1 minute", sollte ein parse-barer Timestamp sein
        $this->assertNotEmpty($manager->receivedSince);
        $this->assertNotFalse(strtotime($manager->receivedSince));
    }

    #[Test]
    public function poll_returns_403_when_not_logged_in(): void
    {
        unset($_SESSION['userid']);

        $manager = new class extends NotificationManager {
            public function __construct() {}
            public function getNewSince(int $userId, string $since): array
            {
                return ['unreadCount' => 0, 'new' => []];
            }
        };

        $controller = $this->makeController($manager);
        $res = $controller->poll(new Request('GET', '/api/notifications/poll'));

        $this->assertSame(403, $res->status);
    }

    #[Test]
    public function mark_read_delegates_to_manager_and_returns_success(): void
    {
        $manager = new class extends NotificationManager {
            public array $lastCall = [];
            public function __construct() {}
            public function markAsRead(int $notificationId, int $userId): bool
            {
                $this->lastCall = ['id' => $notificationId, 'user_id' => $userId];
                return true;
            }
        };

        $controller = $this->makeController($manager);
        $req = new Request(
            method:  'POST',
            path:    '/api/notifications/mark-read',
            rawBody: json_encode(['id' => 7]),
        );

        $res = $controller->markRead($req);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('"success":true', $res->body);
        $this->assertSame(7, $manager->lastCall['id']);
        $this->assertSame(42, $manager->lastCall['user_id']);
    }

    #[Test]
    public function mark_read_returns_404_when_notification_not_found(): void
    {
        $manager = new class extends NotificationManager {
            public function __construct() {}
            public function markAsRead(int $notificationId, int $userId): bool
            {
                return false;
            }
        };

        $controller = $this->makeController($manager);
        $req = new Request(
            method:  'POST',
            path:    '/api/notifications/mark-read',
            rawBody: json_encode(['id' => 99999]),
        );

        $res = $controller->markRead($req);

        $this->assertSame(404, $res->status);
        $this->assertStringContainsString('Notification not found', $res->body);
    }

    #[Test]
    public function mark_read_throws_validation_error_for_missing_id(): void
    {
        $manager = new class extends NotificationManager {
            public function __construct() {}
        };

        $controller = $this->makeController($manager);
        $req = new Request(
            method:  'POST',
            path:    '/api/notifications/mark-read',
            rawBody: json_encode([]),
        );

        $this->expectException(ValidationException::class);
        $controller->markRead($req);
    }

    #[Test]
    public function mark_read_throws_validation_error_for_non_numeric_id(): void
    {
        $manager = new class extends NotificationManager {
            public function __construct() {}
        };

        $controller = $this->makeController($manager);
        $req = new Request(
            method:  'POST',
            path:    '/api/notifications/mark-read',
            rawBody: json_encode(['id' => 'abc']),
        );

        $this->expectException(ValidationException::class);
        $controller->markRead($req);
    }

    #[Test]
    public function mark_read_throws_validation_error_for_zero(): void
    {
        $manager = new class extends NotificationManager {
            public function __construct() {}
        };

        $controller = $this->makeController($manager);
        $req = new Request(
            method:  'POST',
            path:    '/api/notifications/mark-read',
            rawBody: json_encode(['id' => 0]),
        );

        $this->expectException(ValidationException::class);
        $controller->markRead($req);
    }
}
