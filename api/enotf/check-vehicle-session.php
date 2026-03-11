<?php
/**
 * eNOTF Check Vehicle Session
 * Prüft ob ein Fahrzeug eine aktive Session hat und gibt Besatzung + freie Positionen zurück.
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

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$vehicleIdentifier = $_GET['vehicle'] ?? null;

if (!$vehicleIdentifier) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fahrzeug-Kennung fehlt']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM intra_enotf_sessions WHERE vehicle_identifier = :vehicle AND active = 1 ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([':vehicle' => $vehicleIdentifier]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode([
        'success' => true,
        'active' => false
    ]);
    exit;
}

$freePositions = [];
if (empty($session['fahrername'])) $freePositions[] = 'fahrer';
if (empty($session['beifahrername'])) $freePositions[] = 'beifahrer';
if (empty($session['praktikantname'])) $freePositions[] = 'praktikant';

echo json_encode([
    'success' => true,
    'active' => true,
    'session_id' => (int)$session['id'],
    'crew' => [
        'fahrername' => $session['fahrername'],
        'fahrerquali' => $session['fahrerquali'],
        'beifahrername' => $session['beifahrername'],
        'beifahrerquali' => $session['beifahrerquali'],
        'praktikantname' => $session['praktikantname'],
        'praktikantquali' => $session['praktikantquali'],
    ],
    'free_positions' => $freePositions,
    'updated_at' => $session['updated_at']
]);
