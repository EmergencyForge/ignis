<?php

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Notifications\NotificationManager;

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isEnotfUser = !isset($_SESSION['userid']) && isset($_SESSION['fahrername']);

// eNOTF-User dürfen nur Defekte erstellen
if ($isEnotfUser) {
    if ($action !== 'create') {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht authentifiziert']);
        exit;
    }
    // Gemeldete Person aus Formular oder Fallback auf Fahrername
    $reporterName = trim($_POST['reported_by_name'] ?? '') ?: ($_SESSION['fahrername'] ?? '');
    // User-ID über Name aus intra_mitarbeiter → intra_users ermitteln
    $enotfStmt = $pdo->prepare("SELECT u.id FROM intra_users u
        JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
        WHERE m.fullname = :name LIMIT 1");
    $enotfStmt->execute(['name' => $reporterName]);
    $enotfUserId = $enotfStmt->fetchColumn();
    $userId = $enotfUserId ? (int)$enotfUserId : 0;
    $username = $reporterName ?: 'Unbekannt';
} else {
    if (!isset($_SESSION['userid'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht authentifiziert']);
        exit;
    }

    if (!Permissions::check(['admin', 'vehicles.view'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Keine Berechtigung']);
        exit;
    }

    $userId = (int)$_SESSION['userid'];
    $username = $_SESSION['cirs_username'] ?? 'Unbekannt';
}

// Defekt-Kategorien
$allowedCategories = [
    'aufbau_karosserie', 'ausbau', 'batterie', 'beleuchtung', 'bremsen',
    'elektrik', 'fahrwerk', 'getriebe', 'motor', 'reifen',
    'service_pruefintervall', 'signalanlage', 'sonstiges', 'windschutzscheibe'
];

/**
 * Log-Eintrag schreiben
 */
function logDefectAction(PDO $pdo, int $defectId, int $userId, string $action, ?string $details = null): void
{
    $stmt = $pdo->prepare("INSERT INTO intra_fahrzeuge_defect_log (defect_id, user_id, action, details) VALUES (:did, :uid, :action, :details)");
    $stmt->execute(['did' => $defectId, 'uid' => $userId, 'action' => $action, 'details' => $details]);
}

try {
    switch ($action) {

        // ── Liste aller Defekte ──
        case 'list':
            $vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
            $statusFilter = $_GET['status'] ?? '';

            $sql = "SELECT d.*, f.name AS vehicle_name, f.identifier AS vehicle_identifier,
                           f.kennzeichen, f.veh_type,
                           COALESCE(m1.fullname, u1.username) AS reporter_name,
                           COALESCE(m2.fullname, u2.username) AS assigned_name,
                           COALESCE(m3.fullname, u3.username) AS resolver_name
                    FROM intra_fahrzeuge_defects d
                    JOIN intra_fahrzeuge f ON d.vehicle_id = f.id
                    LEFT JOIN intra_users u1 ON d.reported_by = u1.id
                    LEFT JOIN intra_mitarbeiter m1 ON u1.discord_id = m1.discordtag
                    LEFT JOIN intra_users u2 ON d.assigned_to = u2.id
                    LEFT JOIN intra_mitarbeiter m2 ON u2.discord_id = m2.discordtag
                    LEFT JOIN intra_users u3 ON d.resolved_by = u3.id
                    LEFT JOIN intra_mitarbeiter m3 ON u3.discord_id = m3.discordtag
                    WHERE 1=1";
            $params = [];

            if ($vehicleId) {
                $sql .= " AND d.vehicle_id = :vid";
                $params['vid'] = $vehicleId;
            }
            if ($statusFilter && in_array($statusFilter, ['open', 'in_progress', 'deferred', 'resolved'])) {
                $sql .= " AND d.status = :status";
                $params['status'] = $statusFilter;
            }

            $sql .= " ORDER BY FIELD(d.status, 'open', 'in_progress', 'deferred', 'resolved'), CASE WHEN d.status != 'resolved' THEN d.vehicle_operable END ASC, d.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $defects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'defects' => $defects]);
            break;

        // ── Einzelnen Defekt laden (inkl. Log) ──
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Keine ID']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT d.*, f.name AS vehicle_name, f.identifier AS vehicle_identifier,
                                          COALESCE(m1.fullname, u1.username) AS reporter_name,
                                          COALESCE(m2.fullname, u2.username) AS assigned_name,
                                          COALESCE(m3.fullname, u3.username) AS resolver_name
                                   FROM intra_fahrzeuge_defects d
                                   JOIN intra_fahrzeuge f ON d.vehicle_id = f.id
                                   LEFT JOIN intra_users u1 ON d.reported_by = u1.id
                                   LEFT JOIN intra_mitarbeiter m1 ON u1.discord_id = m1.discordtag
                                   LEFT JOIN intra_users u2 ON d.assigned_to = u2.id
                                   LEFT JOIN intra_mitarbeiter m2 ON u2.discord_id = m2.discordtag
                                   LEFT JOIN intra_users u3 ON d.resolved_by = u3.id
                                   LEFT JOIN intra_mitarbeiter m3 ON u3.discord_id = m3.discordtag
                                   WHERE d.id = :id");
            $stmt->execute(['id' => $id]);
            $defect = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) {
                http_response_code(404);
                echo json_encode(['error' => 'Defekt nicht gefunden']);
                exit;
            }

            // Log laden
            $logStmt = $pdo->prepare("SELECT l.*, COALESCE(m.fullname, u.username) AS user_name
                                      FROM intra_fahrzeuge_defect_log l
                                      LEFT JOIN intra_users u ON l.user_id = u.id
                                      LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                                      WHERE l.defect_id = :did
                                      ORDER BY l.created_at ASC");
            $logStmt->execute(['did' => $id]);
            $defect['log'] = $logStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'defect' => $defect]);
            break;

        // ── Neuen Defekt erstellen ──
        case 'create':
            if (!$isEnotfUser && !Permissions::check(['admin', 'vehicles.manage', 'vehicles.view'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'sonstiges';
            $operable = isset($_POST['vehicle_operable']) ? (int)$_POST['vehicle_operable'] : 1;

            if (!$vehicleId || !$title) {
                echo json_encode(['error' => 'Fahrzeug und Titel sind Pflichtfelder']);
                exit;
            }

            if (!$userId && !$isEnotfUser) {
                echo json_encode(['error' => 'Benutzer konnte nicht zugeordnet werden']);
                exit;
            }

            if (!in_array($category, $allowedCategories)) {
                $category = 'sonstiges';
            }

            $stmt = $pdo->prepare("INSERT INTO intra_fahrzeuge_defects (vehicle_id, title, description, category, vehicle_operable, reported_by)
                                   VALUES (:vid, :title, :desc, :cat, :op, :uid)");
            $stmt->execute([
                'vid' => $vehicleId,
                'title' => $title,
                'desc' => $description,
                'cat' => $category,
                'op' => $operable ? 1 : 0,
                'uid' => $userId
            ]);

            $defectId = (int)$pdo->lastInsertId();

            // Log: Erstellt
            $logDetails = 'Defekt gemeldet: ' . $title;
            if ($isEnotfUser && !$userId) {
                $logDetails .= ' (Gemeldet durch: ' . $username . ')';
            }
            logDefectAction($pdo, $defectId, $userId, 'created', $logDetails);

            // Bei nicht einsatzfähig: Fahrzeug deaktivieren
            if (!$operable) {
                $pdo->prepare("UPDATE intra_fahrzeuge SET active = 0 WHERE id = :id")->execute(['id' => $vehicleId]);
                logDefectAction($pdo, $defectId, $userId, 'vehicle_disabled', 'Fahrzeug als nicht einsatzfähig markiert');
            }

            // Benachrichtigung an alle User mit vehicles.view
            try {
                $vehicleNameStmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE id = :id");
                $vehicleNameStmt->execute(['id' => $vehicleId]);
                $vehicleName = $vehicleNameStmt->fetchColumn() ?: 'Unbekannt';

                $notificationManager = new NotificationManager($pdo);

                // Alle User laden, deren Rolle vehicles.view enthält oder die full_admin sind
                $allUsers = $pdo->query("SELECT u.id, u.full_admin, r.permissions
                    FROM intra_users u
                    LEFT JOIN intra_users_roles r ON u.role = r.id
                    WHERE u.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($allUsers as $u) {
                    if ($u['id'] == $userId) continue; // Ersteller nicht benachrichtigen

                    $hasPermission = false;
                    if ($u['full_admin']) {
                        $hasPermission = true;
                    } else {
                        $perms = json_decode($u['permissions'] ?? '[]', true);
                        if (is_array($perms) && (in_array('vehicles.view', $perms) || in_array('admin', $perms))) {
                            $hasPermission = true;
                        }
                    }

                    if ($hasPermission) {
                        $notifTitle = 'Neuer Defekt: ' . $title;
                        $notifMsg = 'Fahrzeug: ' . $vehicleName;
                        if (!$operable) {
                            $notifMsg .= ' — Nicht einsatzfähig!';
                        }
                        $notificationManager->create(
                            (int)$u['id'],
                            'system',
                            $notifTitle,
                            $notifMsg,
                            BASE_PATH . 'settings/fahrzeuge/defekte/index.php'
                        );
                    }
                }
            } catch (\Exception $e) {
                error_log("Defekt-Benachrichtigungsfehler: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'id' => $defectId, 'message' => 'Defekt gemeldet']);
            break;

        // ── Defekt aktualisieren (Status, Zuweisung) ──
        case 'update':
            if (!Permissions::check(['admin', 'vehicles.manage'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
            $statusNote = trim($_POST['status_note'] ?? '');

            if (!$id) {
                echo json_encode(['error' => 'Keine ID']);
                exit;
            }

            // Alten Status laden
            $oldStmt = $pdo->prepare("SELECT status, assigned_to FROM intra_fahrzeuge_defects WHERE id = :id");
            $oldStmt->execute(['id' => $id]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $fields = [];
            $params = ['id' => $id];
            $logMessages = [];

            if ($status && in_array($status, ['open', 'in_progress', 'deferred', 'resolved']) && $status !== ($old['status'] ?? '')) {
                $fields[] = "status = :status";
                $params['status'] = $status;

                $statusNames = ['open' => 'Offen', 'in_progress' => 'In Bearbeitung', 'deferred' => 'Aufgeschoben', 'resolved' => 'Gelöst'];
                $logMsg = 'Status geändert: ' . ($statusNames[$old['status'] ?? 'open'] ?? '?') . ' → ' . ($statusNames[$status] ?? '?');
                if ($statusNote) {
                    $logMsg .= ' | ' . $statusNote;
                }
                $logMessages[] = $logMsg;
            }

            if (array_key_exists('assigned_to', $_POST)) {
                $fields[] = "assigned_to = :assigned";
                $params['assigned'] = $assignedTo;

                if ($assignedTo) {
                    $nameStmt = $pdo->prepare("SELECT COALESCE(m.fullname, u.username) FROM intra_users u LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag WHERE u.id = :id");
                    $nameStmt->execute(['id' => $assignedTo]);
                    $assignedName = $nameStmt->fetchColumn() ?: 'Unbekannt';
                    $logMessages[] = 'Zugewiesen an: ' . $assignedName;
                } else {
                    $logMessages[] = 'Zuweisung entfernt';
                }
            }

            if (empty($fields)) {
                echo json_encode(['error' => 'Keine Änderungen']);
                exit;
            }

            $sql = "UPDATE intra_fahrzeuge_defects SET " . implode(', ', $fields) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);

            // Log schreiben
            foreach ($logMessages as $msg) {
                logDefectAction($pdo, $id, $userId, 'updated', $msg);
            }

            echo json_encode(['success' => true, 'message' => 'Defekt aktualisiert']);
            break;

        // ── Defekt als gelöst markieren ──
        case 'resolve':
            if (!Permissions::check(['admin', 'vehicles.manage'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            $note = trim($_POST['resolution_note'] ?? '');

            if (!$id) {
                echo json_encode(['error' => 'Keine ID']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE intra_fahrzeuge_defects
                                   SET status = 'resolved', resolved_by = :uid, resolved_at = NOW(), resolution_note = :note
                                   WHERE id = :id");
            $stmt->execute(['uid' => $userId, 'note' => $note, 'id' => $id]);

            // Log
            $logDetail = 'Als gelöst markiert';
            if ($note) {
                $logDetail .= ': ' . $note;
            }
            logDefectAction($pdo, $id, $userId, 'resolved', $logDetail);

            // Prüfen ob Fahrzeug wieder einsatzfähig
            $stmt = $pdo->prepare("SELECT d.vehicle_id FROM intra_fahrzeuge_defects d WHERE d.id = :id");
            $stmt->execute(['id' => $id]);
            $defect = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($defect) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fahrzeuge_defects
                                       WHERE vehicle_id = :vid AND vehicle_operable = 0 AND status != 'resolved'");
                $stmt->execute(['vid' => $defect['vehicle_id']]);
                $notOperable = (int)$stmt->fetchColumn();

                if ($notOperable === 0) {
                    $pdo->prepare("UPDATE intra_fahrzeuge SET active = 1 WHERE id = :id")->execute(['id' => $defect['vehicle_id']]);
                    logDefectAction($pdo, $id, $userId, 'vehicle_enabled', 'Fahrzeug wieder einsatzfähig — keine offenen Sperrungen');
                }
            }

            echo json_encode(['success' => true, 'message' => 'Defekt als gelöst markiert']);
            break;

        // ── Defekt löschen ──
        case 'delete':
            if (!Permissions::check(['admin'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Nur Admins können Defekte löschen']);
                exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Keine ID']);
                exit;
            }

            $pdo->prepare("DELETE FROM intra_fahrzeuge_defects WHERE id = :id")->execute(['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Defekt gelöscht']);
            break;

        // ── Log für einen Defekt ──
        case 'log':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Keine ID']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT l.*, COALESCE(m.fullname, u.username) AS user_name
                                   FROM intra_fahrzeuge_defect_log l
                                   LEFT JOIN intra_users u ON l.user_id = u.id
                                   LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                                   WHERE l.defect_id = :did
                                   ORDER BY l.created_at ASC");
            $stmt->execute(['did' => $id]);
            echo json_encode(['success' => true, 'log' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Statistik ──
        case 'stats':
            $vehicleId = (int)($_GET['vehicle_id'] ?? 0);

            $sql = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                        SUM(CASE WHEN status = 'deferred' THEN 1 ELSE 0 END) AS deferred_count,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
                        SUM(CASE WHEN vehicle_operable = 0 AND status != 'resolved' THEN 1 ELSE 0 END) AS not_operable_open
                    FROM intra_fahrzeuge_defects";
            $params = [];

            if ($vehicleId) {
                $sql .= " WHERE vehicle_id = :vid";
                $params['vid'] = $vehicleId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unbekannte Aktion']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
}
