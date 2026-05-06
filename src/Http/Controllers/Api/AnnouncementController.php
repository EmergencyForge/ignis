<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Telemetry\GlobalAnnouncementManager;
use PDO;

/**
 * Global-Announcements-Endpoints.
 *
 * Aktuell: Dismiss einer Announcement durch den aktuellen User. Wird vom
 * Announcements-Modal gerufen, wenn der User "Ausblenden" klickt.
 */
final class AnnouncementController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/announcements/dismiss
     *
     * Body: { "announcement_id": "..." }
     */
    public function dismiss(Request $request): Response
    {
        $input = $request->json();
        if (!is_array($input) || empty($input['announcement_id'])) {
            return Response::json(['success' => false, 'message' => 'announcement_id fehlt'], 400);
        }

        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Nicht autorisiert'], 401);
        }

        try {
            $manager = new GlobalAnnouncementManager($this->pdo);
            $success = $manager->dismissAnnouncement((string) $input['announcement_id'], $userId);
            return Response::json(['success' => $success]);
        } catch (\Throwable $e) {
            Logger::error('Announcement: dismiss fehlgeschlagen', [
                'error' => $e->getMessage(),
                'user'  => $userId,
            ]);
            return Response::json(['success' => false, 'message' => 'Interner Fehler'], 500);
        }
    }
}
