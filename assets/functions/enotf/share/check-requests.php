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

if (!isset($_SESSION['protfzg'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$current_vehicle = $_SESSION['protfzg'];

try {
    // Prüfe auf ausstehende Share-Anfragen für dieses Fahrzeug
    $stmt = $pdo->prepare("
        SELECT 
            sr.id,
            sr.source_enr,
            sr.source_protocol_id,
            sr.source_vehicle,
            sr.created_at,
            ed.enr,
            ed.patname,
            ed.prot_by,
            ed.edatum,
            ed.ezeit
        FROM intra_edivi_share_requests sr
        JOIN intra_edivi ed ON sr.source_protocol_id = ed.id
        WHERE sr.target_vehicle = :vehicle 
        AND sr.status = 'pending'
        ORDER BY sr.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([':vehicle' => $current_vehicle]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        echo json_encode([
            'success' => true,
            'has_requests' => true,
            'request' => $request
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_requests' => false
        ]);
    }
} catch (PDOException $e) {
    error_log("Fehler beim Prüfen auf Share-Anfragen: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
