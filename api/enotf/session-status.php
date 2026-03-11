<?php
/**
 * eNOTF Session Status (Polling Endpoint)
 * Gibt den aktuellen Status der Fahrzeug-Session zurück.
 * Wird alle 10 Sekunden vom Client gepollt.
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

$sessionToken = $_GET['token'] ?? null;

if (!$sessionToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token fehlt']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, m.position AS my_position
    FROM intra_enotf_session_members m
    JOIN intra_enotf_sessions s ON s.id = m.session_id
    WHERE m.session_token = :token
    LIMIT 1
");
$stmt->execute([':token' => $sessionToken]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || (int)$result['active'] === 0) {
    echo json_encode([
        'success' => true,
        'active' => false
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'active' => true,
    'crew' => [
        'fahrername' => $result['fahrername'],
        'fahrerquali' => $result['fahrerquali'],
        'beifahrername' => $result['beifahrername'],
        'beifahrerquali' => $result['beifahrerquali'],
        'praktikantname' => $result['praktikantname'],
        'praktikantquali' => $result['praktikantquali'],
    ],
    'my_position' => $result['my_position'],
    'updated_at' => $result['updated_at']
]);
