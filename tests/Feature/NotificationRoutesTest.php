<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Die Wege zu den Benachrichtigungen: die alte Seite /notifications
 * leitet auf den Posteingang, der Posteingang verlangt die Anmeldung,
 * das Polling der Glocke antwortet ohne Anmeldung mit 401 als JSON.
 * Was der Posteingang zeigt, prüft InboxTest.
 */
class NotificationRoutesTest extends FeatureTestCase
{
    #[Test]
    public function inbox_redirects_unauthenticated(): void
    {
        $response = $this->get('/inbox');

        $this->assertRedirect($response, 'login');
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
    public function old_notifications_page_redirects_to_the_inbox(): void
    {
        foreach (['/notifications', '/notifications/index', '/notifications/index.php'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(301, $response);
            $this->assertSame('/inbox', $response->headers['Location'] ?? null, $path);
        }
    }
}
