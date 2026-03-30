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

// GET: Pending-Fahrzeuge aus der Import-Queue laden (mit Match-Info)
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT q.*
            FROM intra_fahrzeuge_import_queue q
            WHERE q.status = 'pending'
            ORDER BY q.id ASC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Für jedes Fahrzeug prüfen ob es schon existiert und ggf. Daten anhängen
        foreach ($items as &$item) {
            $matchStmt = $pdo->prepare("
                SELECT id, name, identifier, veh_type, rd_type, kennzeichen, priority, active, allowed_jobs
                FROM intra_fahrzeuge
                WHERE name = ? OR identifier = ?
                LIMIT 1
            ");
            $matchStmt->execute([$item['name'], $item['identifier']]);
            $existing = $matchStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $item['existing'] = $existing;
                $item['match_type'] = ($existing['name'] === $item['name']) ? 'name' : 'identifier';
            } else {
                $item['existing'] = null;
                $item['match_type'] = null;
            }
        }
        unset($item);

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

// POST: Neues Fahrzeug importieren
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge_import_queue WHERE id = ? AND status = 'pending'");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden oder bereits verarbeitet']);
            exit();
        }

        // Prüfen ob bereits existiert
        $dupCheck = $pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE name = ? OR identifier = ?");
        $dupCheck->execute([$item['name'], $item['identifier']]);
        if ($dupCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Fahrzeug existiert bereits. Nutze Überschreiben oder Zusammenführen.']);
            exit();
        }

        $name = $item['name'];
        $identifier = $item['identifier'];
        $vehType = trim($_POST['veh_type'] ?? $item['veh_type']);
        $rdType = (int)($_POST['rd_type'] ?? $item['rd_type']);
        $allowedJobs = trim($_POST['allowed_jobs'] ?? $item['job'] ?? '') ?: null;

        $insertStmt = $pdo->prepare("
            INSERT INTO intra_fahrzeuge (name, identifier, veh_type, rd_type, allowed_jobs, priority, active, kennzeichen)
            VALUES (:name, :identifier, :veh_type, :rd_type, :allowed_jobs, 0, 1, '')
        ");
        $insertStmt->execute([
            ':name' => $name,
            ':identifier' => $identifier,
            ':veh_type' => $vehType,
            ':rd_type' => $rdType,
            ':allowed_jobs' => $allowedJobs
        ]);

        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'accepted', processed_at = NOW(), processed_by = ? WHERE id = ?")
            ->execute([$_SESSION['userid'], $queueId]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "Fahrzeug per EMD-Import erstellt", "Name: {$name} | Typ: {$vehType}", 'Fahrzeuge', 1);

        echo json_encode(['success' => true, 'message' => "'{$name}' importiert"]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

// POST: Bestehendes Fahrzeug überschreiben
if ($action === 'overwrite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    $existingId = (int)($_POST['existing_id'] ?? 0);
    if ($queueId <= 0 || $existingId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige IDs']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge_import_queue WHERE id = ? AND status = 'pending'");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden']);
            exit();
        }

        $vehType = trim($_POST['veh_type'] ?? $item['veh_type']);
        $rdType = (int)($_POST['rd_type'] ?? $item['rd_type']);
        $allowedJobs = trim($_POST['allowed_jobs'] ?? $item['job'] ?? '') ?: null;

        $updateStmt = $pdo->prepare("
            UPDATE intra_fahrzeuge
            SET name = :name, identifier = :identifier, veh_type = :veh_type,
                rd_type = :rd_type, allowed_jobs = :allowed_jobs
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':name' => $item['name'],
            ':identifier' => $item['identifier'],
            ':veh_type' => $vehType,
            ':rd_type' => $rdType,
            ':allowed_jobs' => $allowedJobs,
            ':id' => $existingId
        ]);

        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'accepted', processed_at = NOW(), processed_by = ? WHERE id = ?")
            ->execute([$_SESSION['userid'], $queueId]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "Fahrzeug per EMD-Import überschrieben", "Name: {$item['name']} | ID: {$existingId}", 'Fahrzeuge', 1);

        echo json_encode(['success' => true, 'message' => "'{$item['name']}' überschrieben"]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

// POST: Mit bestehendem Fahrzeug zusammenführen (nur leere Felder füllen)
if ($action === 'merge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    $existingId = (int)($_POST['existing_id'] ?? 0);
    if ($queueId <= 0 || $existingId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige IDs']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge_import_queue WHERE id = ? AND status = 'pending'");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden']);
            exit();
        }

        // Bestehendes Fahrzeug laden
        $existStmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE id = ?");
        $existStmt->execute([$existingId]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Bestehendes Fahrzeug nicht gefunden']);
            exit();
        }

        // Nur leere/null Felder übernehmen
        $mergedVehType = !empty($existing['veh_type']) ? $existing['veh_type'] : ($item['veh_type'] ?: '');
        $mergedRdType = ($existing['rd_type'] > 0) ? $existing['rd_type'] : $item['rd_type'];
        $mergedAllowedJobs = !empty($existing['allowed_jobs']) ? $existing['allowed_jobs'] : ($item['job'] ?: null);
        $mergedIdentifier = !empty($existing['identifier']) ? $existing['identifier'] : $item['identifier'];

        $updateStmt = $pdo->prepare("
            UPDATE intra_fahrzeuge
            SET identifier = :identifier, veh_type = :veh_type,
                rd_type = :rd_type, allowed_jobs = :allowed_jobs
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':identifier' => $mergedIdentifier,
            ':veh_type' => $mergedVehType,
            ':rd_type' => $mergedRdType,
            ':allowed_jobs' => $mergedAllowedJobs,
            ':id' => $existingId
        ]);

        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'accepted', processed_at = NOW(), processed_by = ? WHERE id = ?")
            ->execute([$_SESSION['userid'], $queueId]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "Fahrzeug per EMD-Import zusammengeführt", "Name: {$item['name']} | ID: {$existingId}", 'Fahrzeuge', 1);

        echo json_encode(['success' => true, 'message' => "'{$item['name']}' zusammengeführt"]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

// POST: Fahrzeug ignorieren
if ($action === 'ignore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
        exit();
    }

    try {
        $pdo->prepare("UPDATE intra_fahrzeuge_import_queue SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'")
            ->execute([$_SESSION['userid'], $queueId]);

        echo json_encode(['success' => true, 'message' => 'Fahrzeug ignoriert']);
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
