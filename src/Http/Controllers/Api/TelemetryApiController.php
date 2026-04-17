<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Telemetry\GlobalAnnouncementManager;
use App\Telemetry\TelemetryManager;
use PDO;

/**
 * Telemetrie-Endpoints — Heartbeat (API-Key-gated) und Background-AJAX
 * (session-gated).
 *
 * Der Heartbeat-Endpoint wird vom Hub-Server als Machine-to-Machine-Call
 * ausgelöst und per `API_KEY` geschützt (Middleware). Der Background-
 * Endpoint wird aus dem Admin-UI via AJAX gerufen, damit Heartbeat und
 * Announcement-Refresh die Hauptseite nicht blockieren.
 */
final class TelemetryApiController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/telemetry/heartbeat
     *
     * Middleware: ApiKeyMiddleware. Zusätzlich `?force` / `force=1` im
     * Body erzwingt einen Heartbeat auch wenn noch nicht fällig.
     */
    public function heartbeat(Request $request): Response
    {
        try {
            $telemetry = new TelemetryManager($this->pdo);

            if (!$telemetry->isEnabled()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Telemetrie ist deaktiviert',
                ]);
            }

            $force = isset($request->query['force']) || isset($request->post['force']);

            if (!$force && !$telemetry->shouldSendHeartbeat()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Heartbeat noch nicht fällig',
                    'skipped' => true,
                ]);
            }

            return Response::json($telemetry->sendHeartbeat());
        } catch (\Throwable $e) {
            Logger::error('Telemetry: heartbeat Fehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'message' => 'Interner Fehler: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/telemetry/background?action=heartbeat|refresh-announcements
     *
     * Middleware: AuthMiddleware. `heartbeat` zusätzlich admin-only.
     */
    public function background(Request $request): Response
    {
        $action = (string) ($request->query['action'] ?? '');

        switch ($action) {
            case 'heartbeat':
                return $this->backgroundHeartbeat();
            case 'refresh-announcements':
                return $this->backgroundRefreshAnnouncements();
            default:
                return Response::json(['success' => false, 'message' => 'Ungültige Aktion'], 400);
        }
    }

    private function backgroundHeartbeat(): Response
    {
        if (!Gate::check(['admin'])) {
            return Response::json(['success' => false, 'message' => 'Nur für Admins'], 403);
        }

        try {
            $telemetry = new TelemetryManager($this->pdo);
            if ($telemetry->isEnabled() && $telemetry->shouldSendHeartbeat()) {
                return Response::json($telemetry->sendHeartbeat());
            }
            return Response::json(['success' => true, 'skipped' => true]);
        } catch (\Throwable $e) {
            Logger::error('Telemetry: background-heartbeat Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    private function backgroundRefreshAnnouncements(): Response
    {
        try {
            $manager = new GlobalAnnouncementManager($this->pdo);
            return Response::json($manager->refreshCache());
        } catch (\Throwable $e) {
            Logger::error('Telemetry: refresh-announcements Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }
}
