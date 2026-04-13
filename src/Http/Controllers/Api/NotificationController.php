<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Requests\MarkNotificationReadRequest;
use App\Http\Response;
use App\Logging\Logger;
use App\Notifications\NotificationManager;
use Throwable;

/**
 * Notification-API.
 *
 * Migriert aus:
 *   - api/notifications/poll.php       → poll()
 *   - api/notifications/mark-read.php  → markRead()
 *
 * Beide Endpoints laufen hinter `AuthMiddleware` (Session-Auth) — der
 * Controller selbst muss also nichts mehr prüfen, `$_SESSION['userid']`
 * ist garantiert gesetzt, wenn die Methode aufgerufen wird.
 *
 * CSRF ist bewusst NICHT eingehängt: der bestehende Frontend-Code in
 * templates/benachrichtigungen/index.php schickt keinen CSRF-Token mit,
 * ein Upgrade auf CSRF-geschützte Requests braucht eine koordinierte
 * Frontend-Änderung und ist ein eigener Schritt.
 */
final class NotificationController
{
    public function __construct(
        private readonly NotificationManager $notifications,
    ) {}

    /**
     * GET /api/notifications/poll?since=<timestamp>
     *
     * Gibt neue Notifications seit einem Zeitstempel zurück plus den
     * aktuellen Unread-Counter. Wird von der Navbar zyklisch gepollt.
     */
    public function poll(Request $request): Response
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId <= 0) {
            // Sollte durch AuthMiddleware nie vorkommen — Defensive
            return Response::json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        $since = $request->query['since'] ?? date('Y-m-d H:i:s', strtotime('-1 minute') ?: time());
        if (!is_string($since)) {
            $since = date('Y-m-d H:i:s', strtotime('-1 minute') ?: time());
        }

        try {
            $result = $this->notifications->getNewSince($userId, $since);
            return Response::json([
                'success'     => true,
                'unreadCount' => $result['unreadCount'],
                'new'         => $result['new'],
            ]);
        } catch (Throwable $e) {
            Logger::error('NotificationPoll: Fehler', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return Response::json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * POST /api/notifications/mark-read
     *
     * Body: `{"id": <notification_id>}`
     *
     * Markiert eine einzelne Notification als gelesen. Die ID muss
     * dem eingeloggten User gehören, sonst 404.
     */
    public function markRead(Request $request): Response
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        $data           = MarkNotificationReadRequest::validate($request);
        $notificationId = (int) $data['id'];

        try {
            $result = $this->notifications->markAsRead($notificationId, $userId);
            if ($result) {
                return Response::json(['success' => true, 'message' => 'Notification marked as read']);
            }
            return Response::json(['success' => false, 'message' => 'Notification not found'], 404);
        } catch (Throwable $e) {
            Logger::error('NotificationMarkRead: Fehler', [
                'error'           => $e->getMessage(),
                'user_id'         => $userId,
                'notification_id' => $notificationId,
            ]);
            return Response::json(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
