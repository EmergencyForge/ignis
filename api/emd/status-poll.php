<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiges JSON']);
    exit;
}

// API-Key validieren
if (!isset($data['intraRP_API_Key']) || $data['intraRP_API_Key'] !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit;
}

try {
    // Hole alle nicht-zugestellten Statusänderungen
    $stmt = $pdo->prepare("
        SELECT id, vehicle_name, new_status, incident_number, created_at
        FROM intra_fire_status_queue
        WHERE delivered = 0
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusChanges = [];
    $idsToMark = [];

    foreach ($pending as $row) {
        $createdAt = new DateTime($row['created_at']);
        $statusChanges[] = [
            'vehicle_name' => $row['vehicle_name'],
            'status' => $row['new_status'],
            'incident_number' => $row['incident_number'],
            'timestamp' => $createdAt->format('d.m.Y H:i')
        ];
        $idsToMark[] = (int)$row['id'];
    }

    // Markiere als zugestellt
    if (!empty($idsToMark)) {
        $placeholders = implode(',', array_fill(0, count($idsToMark), '?'));
        $updateStmt = $pdo->prepare("UPDATE intra_fire_status_queue SET delivered = 1 WHERE id IN ($placeholders)");
        $updateStmt->execute($idsToMark);
    }

    echo json_encode([
        'success' => true,
        'status_changes' => $statusChanges
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler',
        'message' => $e->getMessage()
    ]);
}
