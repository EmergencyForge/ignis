<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfragemethode']);
    exit();
}

if (!isset($_SESSION['protfzg'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$protocol_id = $input['protocol_id'] ?? null;
$enr = $input['enr'] ?? null;
$target_vehicle = $input['target_vehicle'] ?? null;

if (!$protocol_id || !$enr || !$target_vehicle) {
    echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
    exit();
}

$source_vehicle = $_SESSION['protfzg'];

// Prüfe ob bereits eine pending Anfrage existiert
try {
    $checkStmt = $pdo->prepare("
        SELECT id FROM intra_edivi_share_requests 
        WHERE source_protocol_id = :protocol_id 
        AND target_vehicle = :target_vehicle 
        AND status = 'pending'
    ");
    $checkStmt->execute([
        ':protocol_id' => $protocol_id,
        ':target_vehicle' => $target_vehicle
    ]);

    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Es existiert bereits eine ausstehende Anfrage für dieses Fahrzeug']);
        exit();
    }

    // Erstelle neue Share-Anfrage
    $insertStmt = $pdo->prepare("
        INSERT INTO intra_edivi_share_requests 
        (source_enr, source_protocol_id, source_vehicle, target_vehicle, status, created_at)
        VALUES (:source_enr, :protocol_id, :source_vehicle, :target_vehicle, 'pending', NOW())
    ");

    $insertStmt->execute([
        ':source_enr' => $enr,
        ':protocol_id' => $protocol_id,
        ':source_vehicle' => $source_vehicle,
        ':target_vehicle' => $target_vehicle
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Anfrage wurde erfolgreich gesendet',
        'request_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    error_log("Fehler beim Erstellen der Share-Anfrage: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler beim Senden der Anfrage']);
}
