<?php
/**
 * eNOTF Session Update
 * Aktualisiert die PHP-Session-Variablen des aufrufenden Browsers
 * mit den aktuellen Crew-Daten aus der Fahrzeug-Session.
 * Wird vom Polling-Client aufgerufen wenn eine Crew-Änderung erkannt wurde.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$sessionToken = $_POST['token'] ?? null;

if (!$sessionToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token fehlt']);
    exit;
}

// Aktuelle Crew-Daten aus DB holen
$stmt = $pdo->prepare("
    SELECT s.*
    FROM intra_enotf_session_members m
    JOIN intra_enotf_sessions s ON s.id = m.session_id
    WHERE m.session_token = :token AND s.active = 1
    LIMIT 1
");
$stmt->execute([':token' => $sessionToken]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Session nicht gefunden oder inaktiv']);
    exit;
}

// PHP-Session aktualisieren
$_SESSION['fahrername'] = $session['fahrername'];
$_SESSION['fahrerquali'] = $session['fahrerquali'];
$_SESSION['beifahrername'] = $session['beifahrername'];
$_SESSION['beifahrerquali'] = $session['beifahrerquali'];
$_SESSION['praktikantname'] = $session['praktikantname'];
$_SESSION['praktikantquali'] = $session['praktikantquali'];

// Session-Lock sofort freigeben um Blocking anderer Requests zu vermeiden
session_write_close();

echo json_encode(['success' => true]);
