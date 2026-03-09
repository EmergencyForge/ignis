<?php

/**
 * API: Telemetrie-Heartbeat auslösen
 * 
 * GET/POST /api/telemetry-heartbeat.php
 * 
 * Sicherheit: Nur via API-Key oder localhost
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Telemetry/TelemetryManager.php';

use App\Telemetry\TelemetryManager;

header('Content-Type: application/json');

// Sicherheits-Check: API-Key oder localhost
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$validApiKey = defined('API_KEY') && !empty($apiKey) && $apiKey === API_KEY;

if (!$isLocalhost && !$validApiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

try {
    $telemetry = new TelemetryManager($pdo);

    if (!$telemetry->isEnabled()) {
        echo json_encode(['success' => false, 'message' => 'Telemetrie ist deaktiviert']);
        exit;
    }

    $force = isset($_GET['force']) || isset($_POST['force']);

    if (!$force && !$telemetry->shouldSendHeartbeat()) {
        echo json_encode([
            'success' => true,
            'message' => 'Heartbeat noch nicht fällig',
            'skipped' => true
        ]);
        exit;
    }

    $result = $telemetry->sendHeartbeat();
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Interner Fehler: ' . $e->getMessage()]);
}
