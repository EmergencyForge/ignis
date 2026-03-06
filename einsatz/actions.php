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
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Utils\AuditLogger;
use App\Integrations\DiscordWebhook;
use App\Notifications\NotificationManager;

require __DIR__ . '/../assets/config/database.php';

// Helper function to log actions
function logAction(PDO $pdo, int $incidentId, string $actionType, string $description): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_log (incident_id, action_type, action_description, vehicle_id, operator_id, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $incidentId,
            $actionType,
            $description,
            $_SESSION['einsatz_vehicle_id'] ?? null,
            $_SESSION['einsatz_operator_id'] ?? null,
            $_SESSION['userid'] ?? null
        ]);
    } catch (PDOException $e) {
        // Silently fail - don't stop execution for logging errors
    }
}

$id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$returnTab = $_POST['return_tab'] ?? $_GET['tab'] ?? 'stammdaten';
if ($id <= 0) {
    Flash::error('Ungültige Einsatz-ID');
    header('Location: ' . BASE_PATH . 'index.php');
    exit();
}

// Preload incident for guards
$incident = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM intra_fire_incidents WHERE id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden des Einsatzes.');
    header('Location: ' . BASE_PATH . 'einsatz/view.php?id=' . $id);
    exit();
}

if (!$incident) {
    Flash::error('Einsatz nicht gefunden.');
    header('Location: ' . BASE_PATH . 'index.php');
    exit();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // Add vehicle
        if ($action === 'add_vehicle') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
            $vehicle_name = trim($_POST['vehicle_name'] ?? '');
            $vehicle_identifier = trim($_POST['vehicle_identifier'] ?? '');
            $radio_name = trim($_POST['radio_name'] ?? '');
            $from_other = ($vehicle_id === null) ? 1 : 0;

            if ($vehicle_id !== null) {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incident_vehicles WHERE incident_id = ? AND vehicle_id = ?");
                $dup->execute([$id, $vehicle_id]);
                if ($dup->fetchColumn() > 0) {
                    Flash::error('Fahrzeug bereits hinzugefügt.');
                    goto post_done;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_vehicles (incident_id, vehicle_id, vehicle_name, vehicle_identifier, from_other_org, radio_name, created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$id, $vehicle_id, $vehicle_name ?: null, $vehicle_identifier ?: null, $from_other, $radio_name ?: null, $_SESSION['userid'] ?? null]);

            // Get vehicle name for log
            $displayName = $radio_name ?: $vehicle_name ?: $vehicle_identifier ?: 'Unbekanntes Fahrzeug';
            if ($vehicle_id) {
                $vehStmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE id = ?");
                $vehStmt->execute([$vehicle_id]);
                $vehInfo = $vehStmt->fetch(PDO::FETCH_ASSOC);
                if ($vehInfo) $displayName = $vehInfo['name'];
            }

            logAction(
                $pdo,
                $id,
                'vehicle_added',
                "Fahrzeug '" . $displayName . "' hinzugefügt"
            );

            Flash::success('Einsatzmittel hinzugefügt.');
        }

        // Remove vehicle
        if ($action === 'remove_vehicle') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $rowId = (int)($_POST['vehicle_row_id'] ?? 0);
            if ($rowId > 0) {
                // Get vehicle info before deletion for log
                $vehStmt = $pdo->prepare("SELECT v.radio_name, v.vehicle_name, v.vehicle_identifier, f.name AS sys_name FROM intra_fire_incident_vehicles v LEFT JOIN intra_fahrzeuge f ON v.vehicle_id = f.id WHERE v.id = ? AND v.incident_id = ?");
                $vehStmt->execute([$rowId, $id]);
                $vehInfo = $vehStmt->fetch(PDO::FETCH_ASSOC);
                $displayName = 'Unbekanntes Fahrzeug';
                if ($vehInfo) {
                    $displayName = $vehInfo['radio_name'] ?: $vehInfo['sys_name'] ?: $vehInfo['vehicle_name'] ?: $vehInfo['vehicle_identifier'] ?: 'Unbekanntes Fahrzeug';
                }

                $del = $pdo->prepare("DELETE FROM intra_fire_incident_vehicles WHERE id = ? AND incident_id = ?");
                $del->execute([$rowId, $id]);

                logAction(
                    $pdo,
                    $id,
                    'vehicle_removed',
                    "Fahrzeug '" . $displayName . "' entfernt"
                );

                Flash::success('Fahrzeug entfernt.');
            }
        }

        // Add sitrep
        if ($action === 'add_sitrep') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $rt_date = $_POST['rt_date'] ?? '';
            $rt_time = $_POST['rt_time'] ?? '';
            $text = trim($_POST['text'] ?? '');
            $vehicle_attached_id = !empty($_POST['sitrep_attached_vehicle_id']) ? (int)$_POST['sitrep_attached_vehicle_id'] : null;

            if ($rt_date && $rt_time && $text !== '' && $vehicle_attached_id) {
                $reportDt = DateTime::createFromFormat('Y-m-d H:i', $rt_date . ' ' . $rt_time, new DateTimeZone('Europe/Berlin'));
                $report_time = $reportDt ? $reportDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
                $infoStmt = $pdo->prepare("SELECT v.radio_name, f.name AS sys_name FROM intra_fire_incident_vehicles v LEFT JOIN intra_fahrzeuge f ON v.vehicle_id = f.id WHERE v.id = ? AND v.incident_id = ?");
                $infoStmt->execute([$vehicle_attached_id, $id]);
                $vinfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
                $radio = $vinfo['radio_name'] ?? $vinfo['sys_name'] ?? null;
                $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_sitreps (incident_id, report_time, text, vehicle_radio_name, vehicle_id, created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$id, $report_time, $text, $radio ?: null, null, $_SESSION['userid'] ?? null]);

                logAction(
                    $pdo,
                    $id,
                    'sitrep_added',
                    "Lagemeldung hinzugefügt (Fahrzeug vor Ort: " . ($radio ?: 'Unbekannt') . ")"
                );

                Flash::success('Lagemeldung gespeichert.');
            } else {
                Flash::error('Bitte Datum, Uhrzeit, Text und Fahrzeug vor Ort wählen.');
            }
        }

        // Finalize incident
        if ($action === 'finalize') {
            $stmt = $pdo->prepare("SELECT location, keyword, started_at, leader_id FROM intra_fire_incidents WHERE id = ?");
            $stmt->execute([$id]);
            $inc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inc && $inc['location'] && $inc['keyword'] && $inc['started_at'] && !empty($inc['leader_id'])) {
                $upd = $pdo->prepare("UPDATE intra_fire_incidents SET finalized = 1, finalized_at = NOW(), finalized_by = ?, status = 0 WHERE id = ?");
                $upd->execute([$_SESSION['userid'] ?? null, $id]);

                logAction(
                    $pdo,
                    $id,
                    'finalized',
                    "Einsatz zur QM-Sichtung freigegeben"
                );

                // Incident-Daten für Benachrichtigungen laden
                $stmt = $pdo->prepare("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.id = ?");
                $stmt->execute([$id]);
                $incidentData = $stmt->fetch(PDO::FETCH_ASSOC);

                // Benachrichtigung an Einsatzleiter senden
                try {
                    $notificationManager = new NotificationManager($pdo);
                    $notificationManager->notifyFireProtocolFinalized($incidentData);
                } catch (\Exception $e) {
                    error_log("Fehler beim Senden der Benachrichtigung (Fire Protokoll Freigabe): " . $e->getMessage());
                }

                // Discord Webhook Benachrichtigung senden
                try {

                    if ($incidentData) {
                        $discordWebhook = new DiscordWebhook($pdo);
                        $discordWebhook->notifyFireProtocolReleased($incidentData);
                    }
                } catch (\Exception $e) {
                    // Fehler beim Discord-Webhook loggen, aber Prozess nicht unterbrechen
                    error_log("Discord Webhook Fehler (Fire Protokoll): " . $e->getMessage());
                }

                Flash::success('Protokoll zur QM-Sichtung markiert.');
            } else {
                Flash::error('Pflichtangaben fehlen für Abschluss (inkl. Einsatzleiter).');
            }
        }

        // Set QM status
        if ($action === 'set_status' && Permissions::check(['admin', 'fire.incident.qm'])) {
            $status = (int)($_POST['status'] ?? 0);
            if (in_array($status, [0, 1, 2, 3, 4], true)) {
                $upd = $pdo->prepare("UPDATE intra_fire_incidents SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$status, $_SESSION['userid'] ?? null, $id]);

                $statusLabels = [
                    0 => 'Ungesehen',
                    1 => 'In Prüfung',
                    2 => 'Freigegeben',
                    3 => 'Ungenügend',
                    4 => 'Ausgeblendet'
                ];

                logAction(
                    $pdo,
                    $id,
                    'status_changed',
                    "QM-Status geändert zu '" . $statusLabels[$status] . "'"
                );

                if (isset($_SESSION['userid'])) {
                    $auditLogger = new AuditLogger($pdo);
                    $auditLogger->log($_SESSION['userid'], 'QM-Status geändert [ID: ' . $id . '] → ' . $statusLabels[$status], NULL, 'Feuerwehr', 1);
                }

                // Benachrichtigung an Einsatzleiter senden
                try {
                    $stmt = $pdo->prepare("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.id = ?");
                    $stmt->execute([$id]);
                    $incidentData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($incidentData) {
                        $notificationManager = new NotificationManager($pdo);
                        $userHelper = new UserHelper($pdo);
                        // Hole QM-Username mit der gleichen Methode wie bei eNOTF
                        $qmUsername = $userHelper->getCurrentUserFullnameForAction();
                        $notificationManager->notifyFireProtocolStatusChanged($incidentData, $qmUsername);
                    }
                } catch (\Exception $e) {
                    error_log("Fehler beim Senden der Benachrichtigung (Fire Protokoll Statusänderung): " . $e->getMessage());
                }

                Flash::success('Status aktualisiert.');
            }
        }

        // Update notes (Einsatzgeschehen)
        if ($action === 'update_notes') {
            if (!$incident['finalized']) {
                $notes = trim($_POST['notes'] ?? '');

                $upd = $pdo->prepare("UPDATE intra_fire_incidents SET notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$notes ?: null, $_SESSION['userid'] ?? null, $id]);

                logAction(
                    $pdo,
                    $id,
                    'data_updated',
                    "Einsatzgeschehen aktualisiert"
                );

                Flash::success('Einsatzgeschehen gespeichert.');
            } else {
                Flash::error('Einsatz ist bereits abgeschlossen und kann nicht mehr bearbeitet werden.');
            }
        }

        // Update core data
        if ($action === 'update_core') {
            if (!$incident['finalized']) {
                $loc = trim($_POST['edit_location'] ?? '');
                $keyw = trim($_POST['edit_keyword'] ?? '');
                $incno = trim($_POST['edit_incident_number'] ?? '');
                $date = $_POST['edit_date'] ?? '';
                $time = $_POST['edit_time'] ?? '';
                $leader = !empty($_POST['edit_leader_id']) ? (int)$_POST['edit_leader_id'] : null;
                $caller_name = trim($_POST['edit_caller_name'] ?? '');
                $caller_contact = trim($_POST['edit_caller_contact'] ?? '');
                $owner_name = trim($_POST['edit_owner_name'] ?? '');
                $owner_contact = trim($_POST['edit_owner_contact'] ?? '');

                if ($incno === '' || $loc === '' || $keyw === '' || $date === '' || $time === '' || $leader === null) {
                    Flash::error('Bitte alle Pflichtfelder ausfüllen (Nummer, Ort, Stichwort, Beginn, Einsatzleiter).');
                } else {
                    $startedDt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, new DateTimeZone('Europe/Berlin'));
                    $started = $startedDt ? $startedDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
                    $upd = $pdo->prepare("UPDATE intra_fire_incidents SET incident_number = ?, location = ?, keyword = ?, caller_name = ?, caller_contact = ?, started_at = ?, leader_id = ?, owner_type = NULL, owner_name = ?, owner_contact = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                    $upd->execute([$incno, $loc, $keyw, $caller_name ?: null, $caller_contact ?: null, $started, $leader, $owner_name ?: null, $owner_contact ?: null, $_SESSION['userid'] ?? null, $id]);

                    logAction(
                        $pdo,
                        $id,
                        'data_updated',
                        "Stammdaten aktualisiert"
                    );

                    Flash::success('Einsatzdaten gespeichert.');
                }
            } else {
                Flash::error('Einsatz ist bereits abgeschlossen und kann nicht mehr bearbeitet werden.');
            }
        }

        // Add ASU (Atemschutzüberwachung) protocol
        if ($action === 'add_asu') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $asuDataJson = $_POST['asu_data'] ?? '';
            if (empty($asuDataJson)) {
                Flash::error('Keine ASU-Daten übermittelt.');
                goto post_done;
            }

            $asuData = json_decode($asuDataJson, true);
            if (!$asuData) {
                Flash::error('Ungültige ASU-Daten.');
                goto post_done;
            }

            // Validate required fields
            if (empty($asuData['supervisor']) || empty($asuData['missionNumber']) || empty($asuData['missionLocation']) || empty($asuData['missionDate'])) {
                Flash::error('Pflichtfelder fehlen (Überwacher, Einsatznummer, Ort, Datum).');
                goto post_done;
            }

            // Parse date from DD.MM.YYYY to YYYY-MM-DD
            $dateParts = explode('.', $asuData['missionDate']);
            if (count($dateParts) === 3) {
                $missionDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                $missionDate = $asuData['missionDate'];
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_asu (incident_id, supervisor, mission_location, mission_date, timestamp, data) VALUES (?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $id,
                    $asuData['supervisor'],
                    $asuData['missionLocation'],
                    $missionDate,
                    $asuDataJson
                ]);

                logAction(
                    $pdo,
                    $id,
                    'asu_added',
                    "ASU-Protokoll hinzugefügt (Überwacher: " . $asuData['supervisor'] . ")"
                );

                Flash::success('ASU-Protokoll erfolgreich gespeichert.');
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    Flash::error('Ein ASU-Protokoll für diesen Überwacher existiert bereits.');
                } else {
                    Flash::error('Fehler beim Speichern: ' . $e->getMessage());
                }
            }
        }

        // Update existing ASU protocol
        if ($action === 'update_asu') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $asuId = (int)($_POST['asu_id'] ?? 0);
            if ($asuId <= 0) {
                Flash::error('Keine ASU-ID übermittelt.');
                goto post_done;
            }

            $asuDataJson = $_POST['asu_data'] ?? '';
            if (empty($asuDataJson)) {
                Flash::error('Keine ASU-Daten übermittelt.');
                goto post_done;
            }

            $asuData = json_decode($asuDataJson, true);
            if (!$asuData) {
                Flash::error('Ungültige ASU-Daten.');
                goto post_done;
            }

            if (empty($asuData['supervisor']) || empty($asuData['missionNumber']) || empty($asuData['missionLocation']) || empty($asuData['missionDate'])) {
                Flash::error('Pflichtfelder fehlen (Überwacher, Einsatznummer, Ort, Datum).');
                goto post_done;
            }

            // Parse date from DD.MM.YYYY to YYYY-MM-DD
            $dateParts = explode('.', $asuData['missionDate']);
            if (count($dateParts) === 3) {
                $missionDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                $missionDate = $asuData['missionDate'];
            }

            try {
                $stmt = $pdo->prepare("UPDATE intra_fire_incident_asu SET supervisor = ?, mission_location = ?, mission_date = ?, timestamp = NOW(), data = ? WHERE id = ? AND incident_id = ?");
                $stmt->execute([
                    $asuData['supervisor'],
                    $asuData['missionLocation'],
                    $missionDate,
                    $asuDataJson,
                    $asuId,
                    $id
                ]);

                logAction(
                    $pdo,
                    $id,
                    'asu_updated',
                    "ASU-Protokoll aktualisiert (Überwacher: " . $asuData['supervisor'] . ")"
                );

                Flash::success('ASU-Protokoll erfolgreich aktualisiert.');
            } catch (PDOException $e) {
                Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
            }
        }

        // Delete ASU protocol
        if ($action === 'delete_asu') {
            if ($incident['finalized']) {
                Flash::error('Einsatz ist bereits abgeschlossen.');
                goto post_done;
            }

            $asuId = (int)($_POST['asu_id'] ?? 0);
            if ($asuId > 0) {
                // Get ASU info before deletion for log
                $asuStmt = $pdo->prepare("SELECT supervisor FROM intra_fire_incident_asu WHERE id = ? AND incident_id = ?");
                $asuStmt->execute([$asuId, $id]);
                $asuInfo = $asuStmt->fetch(PDO::FETCH_ASSOC);

                $del = $pdo->prepare("DELETE FROM intra_fire_incident_asu WHERE id = ? AND incident_id = ?");
                $del->execute([$asuId, $id]);

                if ($asuInfo) {
                    logAction(
                        $pdo,
                        $id,
                        'asu_deleted',
                        "ASU-Protokoll gelöscht (Überwacher: " . $asuInfo['supervisor'] . ")"
                    );
                }

                Flash::success('ASU-Protokoll gelöscht.');
            }
        }
    } catch (PDOException $e) {
        Flash::error('Fehler: ' . $e->getMessage());
    }

    // Archive incident (QM only)
    if ($action === 'archive_incident') {
        if (!Permissions::check(['admin', 'fire.incident.qm'])) {
            Flash::error('Keine Berechtigung zum Archivieren von Einsätzen.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE intra_fire_incidents SET archived = 1, archived_at = NOW(), archived_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['userid'] ?? null, $id]);

                logAction(
                    $pdo,
                    $id,
                    'archived',
                    "Einsatz archiviert"
                );

                if (isset($_SESSION['userid'])) {
                    $auditLogger = new AuditLogger($pdo);
                    $auditLogger->log($_SESSION['userid'], 'Einsatz archiviert [ID: ' . $id . ']', NULL, 'Feuerwehr', 1);
                }

                Flash::success('Einsatz wurde archiviert.');
                header('Location: ' . BASE_PATH . 'einsatz/admin/list.php');
                exit();
            } catch (PDOException $e) {
                Flash::error('Fehler beim Archivieren: ' . $e->getMessage());
            }
        }
    }

    // Unarchive incident (QM only)
    if ($action === 'unarchive_incident') {
        if (!Permissions::check(['admin', 'fire.incident.qm'])) {
            Flash::error('Keine Berechtigung zum Wiederherstellen von Einsätzen.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE intra_fire_incidents SET archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?");
                $stmt->execute([$id]);

                logAction(
                    $pdo,
                    $id,
                    'unarchived',
                    "Einsatz wiederhergestellt"
                );

                if (isset($_SESSION['userid'])) {
                    $auditLogger = new AuditLogger($pdo);
                    $auditLogger->log($_SESSION['userid'], 'Einsatz wiederhergestellt [ID: ' . $id . ']', NULL, 'Feuerwehr', 1);
                }

                Flash::success('Einsatz wurde wiederhergestellt.');
                header('Location: ' . BASE_PATH . 'einsatz/admin/list.php');
                exit();
            } catch (PDOException $e) {
                Flash::error('Fehler beim Wiederherstellen: ' . $e->getMessage());
            }
        }
    }

    post_done:
}

// Set flag to skip logging page view after action
$_SESSION['skip_next_view_log'] = true;

// Redirect back to view with tab parameter
header('Location: ' . BASE_PATH . 'einsatz/view.php?id=' . $id . '&tab=' . urlencode($returnTab));
exit();
