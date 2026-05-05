<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Feature-Tests für die Notification-Endpoints.
 *
 * Pilot-Test: zeigt das Muster für weitere Feature-Tests. Deckt die
 * drei häufigsten Auth-Szenarien ab:
 *   - HTML-Route ohne Auth → Redirect
 *   - HTML-Route mit Auth → 200 + Body
 *   - API-Route ohne Auth → 401 JSON
 */
class NotificationRoutesTest extends FeatureTestCase
{
    #[Test]
    public function benachrichtigungen_index_redirects_unauthenticated(): void
    {
        $response = $this->get('/notifications/index');

        $this->assertRedirect($response);
        // AuthMiddleware redirected auf /login.php (relativ zu BASE_PATH)
        $location = $response->headers['Location'] ?? '';
        $this->assertStringContainsString('login', $location);
    }

    #[Test]
    public function notification_poll_api_returns_401_for_unauthenticated(): void
    {
        $response = $this->get('/api/notifications/poll');

        $this->assertUnauthorized($response);
        $body = $this->assertJsonResponse($response);
        $this->assertArrayHasKey('success', $body);
        $this->assertFalse($body['success']);
    }

    #[Test]
    public function benachrichtigungen_index_renders_for_authenticated_user(): void
    {
        // User anlegen + als dieser User einloggen.
        // Transaction-Isolation in IntegrationTestCase cleant das nach dem Test.
        $user = \Tests\FixtureFactory::user();

        $response = $this->actingAs($user->id)
            ->get('/notifications/index');

        $this->assertOk($response);
        $this->assertBodyContains('Benachrichtigungen', $response);
    }
}
