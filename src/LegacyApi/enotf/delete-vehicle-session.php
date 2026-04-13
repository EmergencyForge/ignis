<?php
/**
 * eNOTF Delete Vehicle Session
 * Deaktiviert die aktive Session eines Fahrzeugs (von der Login-Seite aus).
 */
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$vehicle = $input['vehicle'] ?? null;

if (!$vehicle) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fahrzeug fehlt']);
    exit;
}

$stmt = $pdo->prepare("UPDATE intra_enotf_sessions SET active = 0 WHERE vehicle_identifier = :vehicle AND active = 1");
$stmt->execute([':vehicle' => $vehicle]);

echo json_encode(['success' => true]);
