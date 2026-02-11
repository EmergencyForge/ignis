<?php
/**
 * eNOTF Patient-Sync Endpoint
 * Markiert Patientendaten eines Protokolls zum Senden an die Leitstelle.
 * Die Daten werden beim nächsten normalen Fahrzeug-Sync in der Response mitgegeben.
 *
 * Session-Auth (kein API-Key).
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

require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['enr'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

$enr = $data['enr'];

try {
    // Prüfe ob Protokoll existiert und Patientendaten hat
    $stmt = $pdo->prepare("SELECT pat_vorname, pat_nachname, patgebdat, pat_synced FROM intra_edivi WHERE enr = :enr LIMIT 1");
    $stmt->execute([':enr' => $enr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Protokoll nicht gefunden']);
        exit;
    }

    if (empty($row['pat_vorname']) && empty($row['pat_nachname'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Keine Patientendaten vorhanden']);
        exit;
    }

    // Markiere als "zum Senden bereit" (pat_synced = 2)
    $updateStmt = $pdo->prepare("UPDATE intra_edivi SET pat_synced = 2 WHERE enr = :enr");
    $updateStmt->execute([':enr' => $enr]);

    echo json_encode([
        'success' => true,
        'pat_synced' => 2,
        'message' => 'Patientendaten zum Senden markiert'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
