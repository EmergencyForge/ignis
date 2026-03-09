<?php
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

// Session-Auth: Fahrzeug muss eingeloggt sein
if (!isset($_SESSION['einsatz_vehicle_id']) || !isset($_SESSION['einsatz_operator_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

$vehicleId = (int)$_SESSION['einsatz_vehicle_id'];

if ($data['action'] === 'get_status') {
    // Lese aktuellen Status aus intra_fahrzeuge (für Polling vom Frontend)
    try {
        $stmt = $pdo->prepare("
            SELECT current_status, status_source FROM intra_fahrzeuge WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $vehicleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'current_status' => $row['current_status'] ?? null,
            'status_source' => $row['status_source'] ?? null
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    }
    exit;
}

if ($data['action'] === 'set_status') {
    $incidentId = isset($data['incident_id']) ? (int)$data['incident_id'] : 0;
    $newStatus = $data['new_status'] ?? '';

    // Validiere Status (nur 0-6 erlaubt)
    $allowedStatuses = ['0', '1', '2', '3', '4', '5', '6'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiger Status']);
        exit;
    }

    if ($incidentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Einsatz-ID']);
        exit;
    }

    try {
        // Prüfe ob Fahrzeug dem Einsatz zugeordnet ist
        $checkStmt = $pdo->prepare("
            SELECT fiv.id, fi.incident_number
            FROM intra_fire_incident_vehicles fiv
            JOIN intra_fire_incidents fi ON fiv.incident_id = fi.id
            WHERE fiv.vehicle_id = :vehicle_id AND fiv.incident_id = :incident_id
            LIMIT 1
        ");
        $checkStmt->execute([':vehicle_id' => $vehicleId, ':incident_id' => $incidentId]);
        $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Fahrzeug nicht diesem Einsatz zugeordnet']);
            exit;
        }

        $incidentNumber = $assignment['incident_number'];

        // Hole Fahrzeugname aus intra_fahrzeuge
        $vehStmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE id = ? LIMIT 1");
        $vehStmt->execute([$vehicleId]);
        $vehicleName = $vehStmt->fetchColumn() ?: 'Unbekannt';

        $pdo->beginTransaction();

        // 1. Update current_status in intra_fire_incident_vehicles
        $updateStmt = $pdo->prepare("
            UPDATE intra_fire_incident_vehicles
            SET current_status = :status, status_updated_at = NOW()
            WHERE vehicle_id = :vehicle_id AND incident_id = :incident_id
        ");
        $updateStmt->execute([
            ':status' => $newStatus,
            ':vehicle_id' => $vehicleId,
            ':incident_id' => $incidentId
        ]);

        // 2. INSERT in Status-Queue für FiveM-Polling
        $queueStmt = $pdo->prepare("
            INSERT INTO intra_fire_status_queue
            (vehicle_id, vehicle_name, incident_number, new_status)
            VALUES (:vehicle_id, :vehicle_name, :incident_number, :new_status)
        ");
        $queueStmt->execute([
            ':vehicle_id' => $vehicleId,
            ':vehicle_name' => $vehicleName,
            ':incident_number' => $incidentNumber,
            ':new_status' => $newStatus
        ]);

        // 3. Log-Eintrag
        $statusLabels = [
            '0' => 'Dringender Sprechwunsch',
            '1' => 'Einsatzbereit Funk',
            '2' => 'Einsatzbereit Wache',
            '3' => 'Einsatz übernommen',
            '4' => 'Am Einsatzort',
            '5' => 'Sprechwunsch',
            '6' => 'Nicht einsatzbereit',
        ];
        $logStmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_log
            (incident_id, action_type, action_description, vehicle_id, operator_id, created_by)
            VALUES (?, 'status_changed', ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $incidentId,
            "Status auf $newStatus (" . $statusLabels[$newStatus] . ") geändert",
            $vehicleId,
            $_SESSION['einsatz_operator_id'],
            $_SESSION['userid'] ?? null
        ]);

        // 4. Update auch intra_fahrzeuge.current_status (für statusmeldungen.php Anzeige)
        $updateFahrzeugStmt = $pdo->prepare("
            UPDATE intra_fahrzeuge
            SET current_status = :status, status_updated_at = NOW(), status_source = 'incident'
            WHERE id = :id
        ");
        $updateFahrzeugStmt->execute([
            ':status' => $newStatus,
            ':id' => $vehicleId
        ]);

        $pdo->commit();

        echo json_encode(['success' => true, 'new_status' => $newStatus]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
}
