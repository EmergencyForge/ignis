<?php

/**
 * API: Announcement ausblenden (dismiss)
 * 
 * POST /api/dismiss-announcement.php
 * Body: { "announcement_id": "..." }
 */

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../src/Telemetry/GlobalAnnouncementManager.php';

use App\Telemetry\GlobalAnnouncementManager;

header('Content-Type: application/json');

// Nur eingeloggte Benutzer
if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// JSON Body lesen
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['announcement_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'announcement_id fehlt']);
    exit;
}

try {
    $manager = new GlobalAnnouncementManager($pdo);
    $success = $manager->dismissAnnouncement($input['announcement_id'], $_SESSION['userid']);

    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Interner Fehler']);
}
