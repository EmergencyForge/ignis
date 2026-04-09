<?php
// check_dienstnr.php
// Output-Buffer ZUERST starten — verwerft Vendor-Deprecations / Whitespace,
// die sonst den JSON-Parse im Browser sprengen würden.
ob_start();

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (ob_get_length() > 0) {
    ob_clean();
}
// JSON Response Header
header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit();
}

use App\Auth\Permissions;

// Berechtigung prüfen
if (!Permissions::check(['admin', 'personnel.edit'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit();
}

// Nur POST-Requests akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST-Requests erlaubt']);
    exit();
}

try {
    // JSON-Input lesen
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['dienstnr']) || empty($input['dienstnr'])) {
        echo json_encode(['error' => 'Keine Dienstnummer angegeben']);
        exit();
    }

    $dienstnr = trim($input['dienstnr']);

    // Validate format: allow letters, numbers, and hyphens, but require at least one number
    if (!preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
        echo json_encode(['error' => 'Ungültiges Format für Dienstnummer. Muss mindestens eine Zahl enthalten.']);
        exit();
    }

    // Dienstnummer in der Mitarbeiter-Tabelle suchen
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM intra_mitarbeiter WHERE dienstnr = :dienstnr");
    $stmt->execute(['dienstnr' => $dienstnr]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $available = $result['count'] == 0;

    // Erfolgreiche Antwort
    echo json_encode([
        'available' => $available,
        'dienstnr' => $dienstnr,
        'message' => $available ? 'Dienstnummer verfügbar' : 'Dienstnummer bereits vergeben'
    ]);
} catch (Exception $e) {
    // Fehler loggen
    error_log("Dienstnummer Check Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode(['error' => 'Serverfehler beim Prüfen der Dienstnummer']);
}
