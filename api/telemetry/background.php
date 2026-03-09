<?php

/**
 * API: Background-Telemetrie und Announcement-Refresh
 *
 * GET /api/telemetry-background.php?action=heartbeat|refresh-announcements
 *
 * Wird per AJAX aufgerufen, damit die Seite nicht blockiert wird.
 * Authentifizierung über Session (eingeloggter Benutzer).
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Telemetry/TelemetryManager.php';
require_once __DIR__ . '/../../src/Telemetry/GlobalAnnouncementManager.php';

use App\Telemetry\TelemetryManager;
use App\Telemetry\GlobalAnnouncementManager;

header('Content-Type: application/json');

// Nur eingeloggte Benutzer
if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'heartbeat':
        // Nur Admins dürfen Heartbeat senden
        $isAdmin = false;
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $isAdmin = in_array('full_admin', $_SESSION['permissions']) || in_array('admin', $_SESSION['permissions']);
        }

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nur für Admins']);
            exit;
        }

        try {
            $telemetry = new TelemetryManager($pdo);
            if ($telemetry->isEnabled() && $telemetry->shouldSendHeartbeat()) {
                $result = $telemetry->sendHeartbeat();
                echo json_encode($result);
            } else {
                echo json_encode(['success' => true, 'skipped' => true]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        break;

    case 'refresh-announcements':
        try {
            $manager = new GlobalAnnouncementManager($pdo);
            $result = $manager->refreshCache();
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
        break;
}
