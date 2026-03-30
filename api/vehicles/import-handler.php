<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Utils\AuditLogger;

define('VEHICLE_IMPORT_FLAG', __DIR__ . '/../../storage/emd_vehicle_import_request.flag');

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
    exit();
}

if (!Permissions::check(['admin', 'vehicles.manage'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// GET: Pending-Fahrzeuge aus der Import-Queue laden
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT q.*,
                   (SELECT COUNT(*) FROM intra_fahrzeuge f WHERE f.name = q.name OR f.identifier = q.identifier) AS already_exists
            FROM intra_fahrzeuge_import_queue q
            WHERE q.status = 'pending'
            ORDER BY q.id ASC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'vehicles' => $items,
            'count' => count($items)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// POST: Fahrzeug importieren (accept)
if ($action === 'accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
        exit();
    }

    try {
        // Queue-Eintrag laden
        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge_import_queue WHERE id = ? AND status = 'pending'");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden oder bereits verarbeitet']);
            exit();
        }

        // Prüfen ob name oder identifier schon existieren
        $dupCheck = $pdo->prepare("SELECT id, name, identifier FROM intra_fahrzeuge WHERE name = ? OR identifier = ?");
        $dupCheck->execute([$item['name'], $item['identifier']]);
        $existing = $dupCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => false,
                'message' => "Fahrzeug existiert bereits: {$existing['name']} ({$existing['identifier']})"
            ]);
            exit();
        }

        // Overrides aus POST übernehmen (Admin kann Werte im UI anpassen)
        $name = trim($_POST['name'] ?? $item['name']);
        $identifier = trim($_POST['identifier'] ?? $item['identifier']);
        $vehType = trim($_POST['veh_type'] ?? $item['veh_type']);
        $rdType = (int)($_POST['rd_type'] ?? $item['rd_type']);
        $allowedJobs = trim($_POST['allowed_jobs'] ?? $item['job'] ?? '') ?: null;
        $priority = (int)($_POST['priority'] ?? 0);

        // Fahrzeug erstellen
        $insertStmt = $pdo->prepare("
            INSERT INTO intra_fahrzeuge (name, identifier, veh_type, rd_type, allowed_jobs, priority, active, kennzeichen)
            VALUES (:name, :identifier, :veh_type, :rd_type, :allowed_jobs, :priority, 1, '')
        ");
        $insertStmt->execute([
            ':name' => $name,
            ':identifier' => $identifier,
            ':veh_type' => $vehType,
            ':rd_type' => $rdType,
            ':allowed_jobs' => $allowedJobs,
            ':priority' => $priority
        ]);

        $newVehicleId = $pdo->lastInsertId();

        // Queue-Eintrag als akzeptiert markieren
        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'accepted', processed_at = NOW(), processed_by = ? WHERE id = ?")
            ->execute([$_SESSION['userid'], $queueId]);

        // Audit Log
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            "Fahrzeug per EMD-Import erstellt",
            "Name: {$name} | Typ: {$vehType} | Identifier: {$identifier}",
            'Fahrzeuge',
            1
        );

        echo json_encode([
            'success' => true,
            'message' => "Fahrzeug '{$name}' importiert",
            'vehicle_id' => $newVehicleId
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

// POST: Fahrzeug ablehnen (reject)
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
        exit();
    }

    try {
        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'")
            ->execute([$_SESSION['userid'], $queueId]);

        echo json_encode(['success' => true, 'message' => 'Fahrzeug abgelehnt']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// POST: Import anfordern (setzt File-Flag)
if ($action === 'request') {
    try {
        @file_put_contents(VEHICLE_IMPORT_FLAG, date('Y-m-d H:i:s'));

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            'EMD Fahrzeug-Import angefordert',
            'Flag gesetzt - wird beim nächsten Sync übermittelt',
            'Fahrzeuge',
            1
        );

        echo json_encode([
            'success' => true,
            'message' => 'Fahrzeug-Import angefordert. Die Daten werden beim nächsten EMD-Sync übermittelt.'
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// GET: Status prüfen (Flag aktiv? Pending items?)
if ($action === 'status') {
    try {
        $requestPending = file_exists(VEHICLE_IMPORT_FLAG);

        $countStmt = $pdo->query("SELECT COUNT(*) FROM intra_fahrzeuge_import_queue WHERE status = 'pending'");
        $pendingCount = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'request_pending' => $requestPending,
            'import_queue_count' => $pendingCount
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
