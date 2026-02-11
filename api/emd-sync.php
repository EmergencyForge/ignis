<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

function logSync(string $message, string $level = 'INFO'): void
{
    try {
        $logFile = __DIR__ . '/logs/emd_sync.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = __DIR__ . '/logs/emd_sync_' . date('Y-m-d_His') . '.log';
            @rename($logFile, $archiveFile);

            $files = glob(__DIR__ . '/logs/emd_sync_*.log');
            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
            foreach ($files as $file) {
                if (filemtime($file) < $thirtyDaysAgo) {
                    @unlink($file);
                }
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        // Logging-Fehler ignorieren
    }
}

/**
 * Verarbeitet abgeschlossene Einsätze (Dispatch Logs)
 * Wird nur zur Validierung verwendet - keine Speicherung nötig
 *
 * @param array<string, mixed> $data
 */
function handleDispatchLogs(array $data, PDO $pdo): void
{
    try {
        logSync('Dispatch-Log-Sync empfangen (keine Speicherung erforderlich)', 'INFO');

        $missions = $data['missions'] ?? [];

        if (empty($missions)) {
            logSync('Keine Einsätze in der Anfrage', 'INFO');
            echo json_encode(['success' => true, 'message' => 'Keine Einsätze zu verarbeiten', 'processed' => 0]);
            return;
        }

        logSync('Es wurden ' . count($missions) . ' abgeschlossene Einsätze empfangen (werden nicht gespeichert)', 'INFO');

        echo json_encode([
            'success' => true,
            'type' => 'dispatch_logs',
            'processed' => count($missions),
            'total_received' => count($missions),
            'message' => 'Dispatch-Logs empfangen, Statusmeldungen werden über Status-Sync verarbeitet'
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei Dispatch-Log-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Dispatch-Log-Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Verarbeitet Echtzeit-Statusmeldungen und aktualisiert intra_edivi
 *
 * @param array<string, mixed> $data
 */
function handleStatusUpdates(array $data, PDO $pdo): void
{
    try {
        logSync('Starte Status-Update-Verarbeitung', 'INFO');

        $statuses = $data['statuses'] ?? [];

        if (empty($statuses)) {
            logSync('Keine Statusmeldungen in der Anfrage', 'INFO');
            echo json_encode(['success' => true, 'message' => 'Keine Statusmeldungen zu verarbeiten', 'processed' => 0]);
            return;
        }

        logSync('Es wurden ' . count($statuses) . ' Statusmeldungen empfangen', 'INFO');

        $updated = 0;
        $notFound = 0;
        $successfulIds = [];

        foreach ($statuses as $status) {
            $statusValue = $status['status'];
            $missionNumber = $status['mission_number'];
            $sender = $status['sender'];
            $statusId = $status['id'];

            // Konvertiere Zeit-Format
            $statusTime = DateTime::createFromFormat('d.m.Y H:i', $status['timestamp']);
            if (!$statusTime) {
                logSync("Ungültiges Zeitformat für Status-Update $statusId", 'WARNING');
                continue;
            }

            logSync("Verarbeite Status '$statusValue' (Typ: " . gettype($statusValue) . ") für Einsatz $missionNumber von Fahrzeug $sender (ID: $statusId)", 'DEBUG');

            // Mapping: Status -> Spaltenname
            $statusColumn = 's' . $statusValue; // s1, s2, s3, s4, s7, s8

            // Schritt 1: Finde das Fahrzeug-Kennzeichen (identifier) in intra_fahrzeuge
            $findVehicleStmt = $pdo->prepare("
                SELECT identifier, rd_type 
                FROM intra_fahrzeuge 
                WHERE name = :name 
                LIMIT 1
            ");
            $findVehicleStmt->execute([':name' => $sender]);
            $vehicleRow = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$vehicleRow) {
                $notFound++;
                logSync("Fahrzeug $sender nicht in intra_fahrzeuge gefunden - wird beim nächsten Mal erneut versucht (ID: $statusId)", 'WARNING');
                continue;
            }

            $vehicleIdentifier = $vehicleRow['identifier'];
            $rdType = intval($vehicleRow['rd_type'] ?? 0);

            logSync("Fahrzeug $sender hat Kennzeichen: $vehicleIdentifier (rd_type=$rdType)", 'DEBUG');

            // Schritt 2: Suche in intra_edivi nach dem Einsatz, wo dieses Kennzeichen in fzg_na oder fzg_transp steht
            $findEnrStmt = $pdo->prepare("
                SELECT enr 
                FROM intra_edivi 
                WHERE (enr = :mission OR enr LIKE :mission_pattern)
                  AND (fzg_na = :vehicle_id1 OR fzg_transp = :vehicle_id2)
                LIMIT 1
            ");
            $findEnrStmt->execute([
                ':mission' => $missionNumber,
                ':mission_pattern' => $missionNumber . '_%',
                ':vehicle_id1' => $vehicleIdentifier,
                ':vehicle_id2' => $vehicleIdentifier
            ]);
            $enrRow = $findEnrStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrRow) {
                $notFound++;
                logSync("Einsatz mit Kennzeichen $vehicleIdentifier für Mission $missionNumber nicht in intra_edivi gefunden - wird beim nächsten Mal erneut versucht (ID: $statusId)", 'WARNING');
                continue;
            }

            $enr = $enrRow['enr'];
            $formattedTime = $statusTime->format('Y-m-d H:i:s');

            logSync("Fahrzeug $sender (Kennzeichen: $vehicleIdentifier) ist Einsatz $enr zugeordnet", 'DEBUG');

            // Schritt 3: Update NUR diesen spezifischen Einsatz
            // Status C ist die Alarmierung - wird in salarm gespeichert
            // Status 1, 2, 3, 4, 7, 8 werden in s1-s8 gespeichert

            if (strtoupper(trim($statusValue)) === 'C') {
                // Status C (Alarmierung) -> nur salarm
                logSync("Versuche salarm zu setzen für ENR $enr mit Zeit $formattedTime", 'DEBUG');
                $sql = "UPDATE intra_edivi SET salarm = :salarm WHERE enr = :enr";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute([
                    ':salarm' => $formattedTime,
                    ':enr' => $enr
                ]);
                $rowCount = $updateStmt->rowCount();
                logSync("Status C (Alarmierung) für Fahrzeug $sender in Einsatz $enr: salarm = $formattedTime, rowCount = $rowCount (ID: $statusId)", 'INFO');
            } else {
                // Status 1-8 -> in s1-s8 Spalten
                $allowedColumns = ['s1', 's2', 's3', 's4', 's7', 's8'];
                if (!in_array($statusColumn, $allowedColumns)) {
                    logSync("Ungültige Status-Spalte: $statusColumn (ID: $statusId)", 'WARNING');
                    continue;
                }

                $sql = "UPDATE intra_edivi SET $statusColumn = :status_time WHERE enr = :enr";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute([
                    ':status_time' => $formattedTime,
                    ':enr' => $enr
                ]);
                logSync("Status $statusValue für Fahrzeug $sender in Einsatz $enr: $statusColumn = $formattedTime (ID: $statusId)", 'INFO');
            }

            $updatedThisStatus = false;
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                $updatedThisStatus = true;
            } else {
                logSync("Status $statusValue für Einsatz $enr war bereits gesetzt oder Spalte existiert nicht", 'WARNING');
            }

            // Zusätzlich: Update Fire Incident Status für Feuerwehr-Fahrzeuge (rd_type = 3)
            if ($rdType === 3) {
                // Suche nach Fire Incident mit dieser Einsatznummer
                $findFireIncidentStmt = $pdo->prepare("
                    SELECT id FROM intra_fire_incidents 
                    WHERE incident_number = :incident_number 
                    LIMIT 1
                ");
                $findFireIncidentStmt->execute([':incident_number' => (string)$missionNumber]);
                $fireIncidentRow = $findFireIncidentStmt->fetch(PDO::FETCH_ASSOC);

                if ($fireIncidentRow) {
                    $fireIncidentId = (int)$fireIncidentRow['id'];

                    // Hole die vehicle_id aus der Datenbank
                    $getVehicleIdStmt = $pdo->prepare("
                        SELECT id FROM intra_fahrzeuge 
                        WHERE identifier = :identifier 
                        LIMIT 1
                    ");
                    $getVehicleIdStmt->execute([':identifier' => $vehicleIdentifier]);
                    $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

                    if ($vehicleIdRow) {
                        $vehicleId = (int)$vehicleIdRow['id'];

                        // Update Status in intra_fire_incident_vehicles
                        $updateFireStatusStmt = $pdo->prepare("
                            UPDATE intra_fire_incident_vehicles 
                            SET current_status = :status, status_updated_at = NOW() 
                            WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                        ");
                        $updateFireStatusStmt->execute([
                            ':status' => (string)$statusValue,
                            ':incident_id' => $fireIncidentId,
                            ':vehicle_id' => $vehicleId
                        ]);

                        if ($updateFireStatusStmt->rowCount() > 0) {
                            logSync("Fire Incident Status aktualisiert: Fahrzeug $sender (ID: $vehicleId) in Incident #$fireIncidentId auf Status $statusValue", 'INFO');
                        }
                    }
                }
            }

            // Nur zu successfulIds hinzufügen, wenn mindestens ein Update erfolgreich war
            if ($updatedThisStatus) {
                $successfulIds[] = $statusId;
            }
        }

        logSync("Status-Update-Verarbeitung abgeschlossen: $updated intra_edivi-Updates, $notFound nicht gefunden von " . count($statuses) . " Statusmeldungen", 'INFO');

        echo json_encode([
            'success' => true,
            'type' => 'status_updates',
            'updated_edivi' => $updated,
            'not_found' => $notFound,
            'successful_ids' => $successfulIds,
            'total_received' => count($statuses)
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei Status-Update-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Status-Update-Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Verarbeitet Statusmeldungen für Fahrzeuge ohne aktiven Dispatch
 * Aktualisiert current_status direkt auf intra_fahrzeuge
 *
 * @param array<string, mixed> $data
 */
function handleStatusNoDispatch(array $data, PDO $pdo): void
{
    try {
        $vehicleName = $data['vehicle_name'] ?? '';
        $newStatus = $data['status'] ?? '';
        $statusTime = $data['time'] ?? '';
        $statusDate = $data['date'] ?? '';

        if (empty($vehicleName) || $newStatus === '') {
            logSync("status_no_dispatch: Fehlende Daten (vehicle_name='$vehicleName', status='$newStatus')", 'WARNING');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Fehlende Pflichtfelder (vehicle_name, status)']);
            return;
        }

        // Parse Zeitstempel
        $updatedAt = null;
        if (!empty($statusDate) && !empty($statusTime)) {
            $dt = DateTime::createFromFormat('d.m.Y H:i', $statusDate . ' ' . $statusTime, new DateTimeZone('Europe/Berlin'));
            if ($dt) {
                $updatedAt = $dt->format('Y-m-d H:i:s');
            }
        }
        if (!$updatedAt) {
            $updatedAt = date('Y-m-d H:i:s');
        }

        // Finde das Fahrzeug in der DB
        $findVehicleStmt = $pdo->prepare("
            SELECT id, name FROM intra_fahrzeuge WHERE name = :name LIMIT 1
        ");
        $findVehicleStmt->execute([':name' => $vehicleName]);
        $vehicle = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            logSync("status_no_dispatch: Fahrzeug '$vehicleName' nicht in intra_fahrzeuge gefunden", 'WARNING');
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Fahrzeug nicht gefunden']);
            return;
        }

        $vehicleId = (int)$vehicle['id'];

        // Update Status auf intra_fahrzeuge
        $updateStmt = $pdo->prepare("
            UPDATE intra_fahrzeuge
            SET current_status = :status, status_updated_at = :updated_at, status_source = 'no_dispatch'
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':status' => (string)$newStatus,
            ':updated_at' => $updatedAt,
            ':id' => $vehicleId
        ]);

        logSync("status_no_dispatch: Fahrzeug '$vehicleName' (ID: $vehicleId) Status auf $newStatus gesetzt (source=no_dispatch, time=$updatedAt)", 'INFO');

        echo json_encode([
            'success' => true,
            'vehicle_id' => $vehicleId,
            'new_status' => $newStatus,
            'source' => 'no_dispatch'
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei status_no_dispatch: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Verarbeitet Status-Updates intern und gibt Ergebnisse zurück
 * Wird sowohl von v1 handleStatusUpdates als auch vom v2 Unified Handler verwendet
 *
 * @param array<array<string, mixed>> $statuses
 * @return array{updated: int, not_found: int, successful_ids: array<string|int>, total: int}
 */
function processStatusUpdatesInternal(array $statuses, PDO $pdo): array
{
    $updated = 0;
    $notFound = 0;
    $successfulIds = [];

    foreach ($statuses as $status) {
        $statusValue = $status['status'];
        $missionNumber = $status['mission_number'];
        $sender = $status['sender'];
        $statusId = $status['id'];

        $statusTime = DateTime::createFromFormat('d.m.Y H:i', $status['timestamp']);
        if (!$statusTime) {
            logSync("Ungültiges Zeitformat für Status-Update $statusId", 'WARNING');
            continue;
        }

        logSync("Verarbeite Status '$statusValue' für Einsatz $missionNumber von Fahrzeug $sender (ID: $statusId)", 'DEBUG');

        $statusColumn = 's' . $statusValue;

        $findVehicleStmt = $pdo->prepare("
            SELECT identifier, rd_type
            FROM intra_fahrzeuge
            WHERE name = :name
            LIMIT 1
        ");
        $findVehicleStmt->execute([':name' => $sender]);
        $vehicleRow = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicleRow) {
            $notFound++;
            logSync("Fahrzeug $sender nicht gefunden (ID: $statusId)", 'WARNING');
            continue;
        }

        $vehicleIdentifier = $vehicleRow['identifier'];
        $rdType = intval($vehicleRow['rd_type'] ?? 0);

        $findEnrStmt = $pdo->prepare("
            SELECT enr
            FROM intra_edivi
            WHERE (enr = :mission OR enr LIKE :mission_pattern)
              AND (fzg_na = :vehicle_id1 OR fzg_transp = :vehicle_id2)
            LIMIT 1
        ");
        $findEnrStmt->execute([
            ':mission' => $missionNumber,
            ':mission_pattern' => $missionNumber . '_%',
            ':vehicle_id1' => $vehicleIdentifier,
            ':vehicle_id2' => $vehicleIdentifier
        ]);
        $enrRow = $findEnrStmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrRow) {
            $notFound++;
            logSync("Einsatz für $vehicleIdentifier / $missionNumber nicht gefunden (ID: $statusId)", 'WARNING');
            continue;
        }

        $enr = $enrRow['enr'];
        $formattedTime = $statusTime->format('Y-m-d H:i:s');

        if (strtoupper(trim($statusValue)) === 'C') {
            $sql = "UPDATE intra_edivi SET salarm = :salarm WHERE enr = :enr";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute([':salarm' => $formattedTime, ':enr' => $enr]);
            logSync("Status C für $sender: salarm = $formattedTime (ID: $statusId)", 'INFO');
        } else {
            $allowedColumns = ['s1', 's2', 's3', 's4', 's7', 's8'];
            if (!in_array($statusColumn, $allowedColumns)) {
                logSync("Ungültige Status-Spalte: $statusColumn (ID: $statusId)", 'WARNING');
                continue;
            }
            $sql = "UPDATE intra_edivi SET $statusColumn = :status_time WHERE enr = :enr";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute([':status_time' => $formattedTime, ':enr' => $enr]);
            logSync("Status $statusValue für $sender: $statusColumn = $formattedTime (ID: $statusId)", 'INFO');
        }

        $updatedThisStatus = false;
        if ($updateStmt->rowCount() > 0) {
            $updated++;
            $updatedThisStatus = true;
        }

        // Fire Incident Status für Feuerwehr-Fahrzeuge (rd_type = 3)
        if ($rdType === 3) {
            $findFireIncidentStmt = $pdo->prepare("SELECT id FROM intra_fire_incidents WHERE incident_number = :incident_number LIMIT 1");
            $findFireIncidentStmt->execute([':incident_number' => (string)$missionNumber]);
            $fireIncidentRow = $findFireIncidentStmt->fetch(PDO::FETCH_ASSOC);

            if ($fireIncidentRow) {
                $fireIncidentId = (int)$fireIncidentRow['id'];
                $getVehicleIdStmt = $pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE identifier = :identifier LIMIT 1");
                $getVehicleIdStmt->execute([':identifier' => $vehicleIdentifier]);
                $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

                if ($vehicleIdRow) {
                    $vehicleId = (int)$vehicleIdRow['id'];
                    $updateFireStatusStmt = $pdo->prepare("
                        UPDATE intra_fire_incident_vehicles
                        SET current_status = :status, status_updated_at = NOW()
                        WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                    ");
                    $updateFireStatusStmt->execute([
                        ':status' => (string)$statusValue,
                        ':incident_id' => $fireIncidentId,
                        ':vehicle_id' => $vehicleId
                    ]);
                    if ($updateFireStatusStmt->rowCount() > 0) {
                        logSync("Fire Incident Status: $sender (ID: $vehicleId) in #$fireIncidentId -> $statusValue", 'INFO');
                    }
                }
            }
        }

        if ($updatedThisStatus) {
            $successfulIds[] = $statusId;
        }
    }

    logSync("processStatusUpdatesInternal: $updated Updates, $notFound nicht gefunden von " . count($statuses), 'INFO');

    return [
        'updated' => $updated,
        'not_found' => $notFound,
        'successful_ids' => $successfulIds,
        'total' => count($statuses)
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $receivedData = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logSync('Ungültiges JSON empfangen: ' . json_last_error_msg(), 'ERROR');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiges JSON']);
        exit;
    }

    if (!isset($receivedData['intraRP_API_Key']) || $receivedData['intraRP_API_Key'] !== API_KEY) {
        logSync('Unberechtigter Zugriffsversuch', 'WARNING');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Nicht autorisiert',
            'hint' => 'API-Key stimmt nicht überein'
        ]);
        exit;
    }

    // Letzten Sync-Zeitpunkt speichern (für Verbindungs-Status in der Topbar)
    @file_put_contents(__DIR__ . '/../storage/last_emd_sync.txt', date('Y-m-d H:i:s'));

    // Routing basierend auf type
    if (isset($receivedData['type'])) {
        switch ($receivedData['type']) {
            case 'dispatch_logs':
                handleDispatchLogs($receivedData, $pdo);
                exit;
            case 'status_updates':
                handleStatusUpdates($receivedData, $pdo);
                exit;
            case 'status_no_dispatch':
                handleStatusNoDispatch($receivedData, $pdo);
                exit;
            default:
                logSync('Unbekannter Sync-Typ: ' . $receivedData['type'], 'WARNING');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unbekannter Sync-Typ']);
                exit;
        }
    }

    // Protocol v2: Unified Request Handling
    $isV2 = isset($receivedData['protocol_version']) && (int)$receivedData['protocol_version'] === 2;
    $v2StatusResult = ['successful_ids' => []];

    if ($isV2) {
        logSync('Protocol v2 erkannt - Unified Request', 'INFO');

        // 1. Status-Updates verarbeiten (vor Fahrzeug-Sync)
        $v2Statuses = $receivedData['status_updates']['statuses'] ?? [];
        if (!empty($v2Statuses)) {
            logSync('V2: Verarbeite ' . count($v2Statuses) . ' Status-Updates', 'INFO');
            $v2StatusResult = processStatusUpdatesInternal($v2Statuses, $pdo);
        }

        // 2. Fallback-Statuses verarbeiten (Fahrzeuge ohne aktiven Dispatch)
        $v2Fallbacks = $receivedData['fallback_statuses'] ?? [];
        if (!empty($v2Fallbacks)) {
            logSync('V2: Verarbeite ' . count($v2Fallbacks) . ' Fallback-Statuses', 'INFO');
            foreach ($v2Fallbacks as $fb) {
                $fbVehicleName = $fb['vehicle_name'] ?? '';
                $fbStatus = $fb['status'] ?? '';
                if (empty($fbVehicleName) || $fbStatus === '') continue;

                $fbUpdatedAt = date('Y-m-d H:i:s');
                if (!empty($fb['date']) && !empty($fb['time'])) {
                    $fbDt = DateTime::createFromFormat('d.m.Y H:i', $fb['date'] . ' ' . $fb['time'], new DateTimeZone('Europe/Berlin'));
                    if ($fbDt) $fbUpdatedAt = $fbDt->format('Y-m-d H:i:s');
                }

                $fbStmt = $pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE name = :name LIMIT 1");
                $fbStmt->execute([':name' => $fbVehicleName]);
                $fbVehicle = $fbStmt->fetch(PDO::FETCH_ASSOC);
                if (!$fbVehicle) {
                    logSync("V2 Fallback: Fahrzeug '$fbVehicleName' nicht gefunden", 'WARNING');
                    continue;
                }

                $pdo->prepare("UPDATE intra_fahrzeuge SET current_status = :status, status_updated_at = :updated_at, status_source = 'no_dispatch' WHERE id = :id")
                    ->execute([':status' => (string)$fbStatus, ':updated_at' => $fbUpdatedAt, ':id' => (int)$fbVehicle['id']]);
                logSync("V2 Fallback: Fahrzeug '$fbVehicleName' Status auf $fbStatus", 'INFO');
            }
        }

        // 3. Dispatch-Logs: Nur Logging (keine Speicherung nötig)
        if (isset($receivedData['dispatch_logs'])) {
            $dlMissions = $receivedData['dispatch_logs']['missions'] ?? [];
            logSync('V2: ' . count($dlMissions) . ' Dispatch-Logs empfangen (keine Speicherung)', 'INFO');
        }

        // 4. Normalisiere dispatch_data -> data für bestehenden Fahrzeug-Sync
        if (isset($receivedData['dispatch_data'])) {
            $receivedData['data'] = $receivedData['dispatch_data'];
        } elseif (!isset($receivedData['data'])) {
            $receivedData['data'] = ['vehicles' => []];
        }
    } else {
        $isV2 = false;
    }

    // Normale Fahrzeug-Synchronisierung
    $vehicles = $receivedData['data']['vehicles'] ?? [];

    $vehiclesByDispatch = [];
    $dispatchDataByDispatch = [];

    if (empty($vehicles)) {
        logSync('Keine Fahrzeuge in der Anfrage', 'INFO');
    } else {
        logSync('Es wurden ' . count($vehicles) . ' Fahrzeuge zur Verarbeitung empfangen', 'INFO');

        foreach ($vehicles as $vehicle) {
            $dispatchId = intval($vehicle['dispatch'] ?? 0);

            if ($dispatchId <= 0) {
                continue;
            }

            if (!isset($vehiclesByDispatch[$dispatchId])) {
                $vehiclesByDispatch[$dispatchId] = [];
            }

            $vehiclesByDispatch[$dispatchId][] = $vehicle;

            // Speichere dispatch_data (falls vorhanden)
            if (isset($vehicle['dispatch_data']) && !isset($dispatchDataByDispatch[$dispatchId])) {
                $dispatchDataByDispatch[$dispatchId] = $vehicle['dispatch_data'];
                logSync("Dispatch-Data für Einsatz #$dispatchId empfangen: " . json_encode($vehicle['dispatch_data']), 'DEBUG');
            }
        }

        // Debug: Zeige alle empfangenen dispatch_data
        if (!empty($dispatchDataByDispatch)) {
            logSync("Insgesamt " . count($dispatchDataByDispatch) . " Dispatch-Daten empfangen", 'INFO');
        } else {
            logSync("Keine dispatch_data in den Fahrzeugdaten gefunden - prüfe Datenstruktur", 'WARNING');
            logSync("Beispiel-Fahrzeug-Datenstruktur: " . json_encode($vehicles[0]), 'DEBUG');
        }

        logSync('Es wurden ' . count($vehiclesByDispatch) . ' eindeutige Einsatznummern gefunden', 'INFO');
    }

    $processedDispatches = 0;
    $createdEntries = 0;
    $skippedDispatches = 0;
    $createdFireIncidents = 0;
    $newSitrepsFromDispatch = 0;
    $fireIncidentsByDispatch = []; // Dispatch-ID -> Fire Incident ID mapping

    $pdo->beginTransaction();

    foreach ($vehiclesByDispatch as $dispatchId => $dispatchVehicles) {
        logSync("Verarbeite Einsatz #$dispatchId mit " . count($dispatchVehicles) . " Fahrzeugen", 'INFO');

        $validVehicles = [];

        foreach ($dispatchVehicles as $vehicle) {
            $valueName = $vehicle['value'] ?? null;

            $stmt = $pdo->prepare("
                SELECT id, name, identifier, veh_type, rd_type
                FROM intra_fahrzeuge 
                WHERE name = :name 
                LIMIT 1
            ");
            $stmt->execute([':name' => $valueName]);
            $dbVehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbVehicle) {
                logSync("Fahrzeug '$valueName' wurde in der Datenbank nicht gefunden, wird übersprungen", 'WARNING');
                continue;
            }

            $rdType = intval($dbVehicle['rd_type'] ?? 0);

            if ($rdType === 0) {
                logSync("Fahrzeug '$valueName' ist kein RD-Fahrzeug (rd_type=0), wird übersprungen", 'DEBUG');
                continue;
            }

            $validVehicles[] = [
                'name' => $valueName,
                'identifier' => $dbVehicle['identifier'],
                'rd_type' => $rdType,
                'is_notarzt' => ($rdType === 1),
                'is_transport' => ($rdType === 2)
            ];
        }

        if (empty($validVehicles)) {
            logSync("Keine gültigen RD-Fahrzeuge für Einsatz #$dispatchId, wird übersprungen", 'WARNING');
            $skippedDispatches++;
            continue;
        }

        logSync("Es wurden " . count($validVehicles) . " gültige RD-Fahrzeuge für Einsatz #$dispatchId gefunden", 'INFO');

        // Prüfe ob es sich um einen Feuerwehreinsatz handelt (rd_type = 3)
        $hasFireVehicle = false;
        $fireVehicles = [];
        foreach ($validVehicles as $vehicle) {
            if ($vehicle['rd_type'] === 3) {
                $hasFireVehicle = true;
                $fireVehicles[] = $vehicle;
            }
        }

        // Wenn Feuerwehreinsatz: Erstelle Fire Incident
        if ($hasFireVehicle && count($fireVehicles) > 0) {
            logSync("Feuerwehreinsatz erkannt (#$dispatchId) mit " . count($fireVehicles) . " Feuerwehrfahrzeugen", 'INFO');

            // Prüfe ob bereits ein Fire Incident für diese Dispatch-ID existiert
            $checkFireIncidentStmt = $pdo->prepare("
                SELECT id FROM intra_fire_incidents 
                WHERE incident_number = :incident_number
                LIMIT 1
            ");
            $checkFireIncidentStmt->execute([':incident_number' => (string)$dispatchId]);
            $existingFireIncident = $checkFireIncidentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingFireIncident) {
                // Erstelle neuen Fire Incident
                $currentDateTime = date('Y-m-d H:i:s');

                // Hole dispatch_data falls vorhanden
                $dispatchData = $dispatchDataByDispatch[$dispatchId] ?? null;
                $location = 'BITTE ÄNDERN!';
                $keyword = 'BITTE ÄNDERN!';
                $dispatchIssue = '';
                $callerName = '';
                $callerContact = '';
                $locationX = null;
                $locationY = null;

                logSync("Dispatch #$dispatchId: dispatch_data vorhanden: " . ($dispatchData ? 'JA' : 'NEIN'), 'DEBUG');

                if ($dispatchData) {
                    logSync("Dispatch #$dispatchId dispatch_data Inhalt: " . json_encode($dispatchData), 'DEBUG');

                    // Verwende postal für location
                    if (!empty($dispatchData['postal'])) {
                        $location = $dispatchData['postal'];
                        logSync("Dispatch #$dispatchId: Location aus postal gesetzt: $location", 'DEBUG');
                    } else {
                        logSync("Dispatch #$dispatchId: postal ist leer oder nicht vorhanden", 'WARNING');
                    }

                    // Verwende location_x und location_y für GTA Koordinaten (optional)
                    if (!empty($dispatchData['location_x'])) {
                        $locationX = (float)$dispatchData['location_x'];
                        logSync("Dispatch #$dispatchId: location_x gesetzt: $locationX", 'DEBUG');
                    }
                    if (!empty($dispatchData['location_y'])) {
                        $locationY = (float)$dispatchData['location_y'];
                        logSync("Dispatch #$dispatchId: location_y gesetzt: $locationY", 'DEBUG');
                    }

                    // Verwende dispatch_code für keyword
                    if (!empty($dispatchData['dispatch_code'])) {
                        $keyword = $dispatchData['dispatch_code'];
                        logSync("Dispatch #$dispatchId: Keyword aus dispatch_code gesetzt: $keyword", 'DEBUG');
                    } else {
                        logSync("Dispatch #$dispatchId: dispatch_code ist leer oder nicht vorhanden", 'WARNING');
                    }

                    // Verwende dispatch_issue für notes
                    if (!empty($dispatchData['dispatch_issue'])) {
                        $dispatchIssue = $dispatchData['dispatch_issue'];
                        logSync("Dispatch #$dispatchId: dispatch_issue gefunden: " . substr($dispatchIssue, 0, 50) . "...", 'DEBUG');
                    }

                    // Verwende caller_name für Melder Name
                    if (!empty($dispatchData['caller_name'])) {
                        $callerName = $dispatchData['caller_name'];
                        logSync("Dispatch #$dispatchId: caller_name gesetzt: $callerName", 'DEBUG');
                    }

                    // Verwende caller_phonenumber für Melder Kontakt
                    if (!empty($dispatchData['caller_phonenumber'])) {
                        $callerContact = $dispatchData['caller_phonenumber'];
                        logSync("Dispatch #$dispatchId: caller_contact gesetzt: $callerContact", 'DEBUG');
                    }
                } else {
                    logSync("Dispatch #$dispatchId: Keine dispatch_data vorhanden, verwende Fallbacks", 'WARNING');
                }

                // Erstelle notes mit dispatch_issue und System-Hinweis
                $notes = '';
                if (!empty($dispatchIssue)) {
                    $notes = 'EINSATZMELDUNG: ' . $dispatchIssue . "\n\n";
                }
                $notes .= 'Automatisch erstellt durch Synchronisation';

                $insertFireIncidentStmt = $pdo->prepare("
                    INSERT INTO intra_fire_incidents 
                    (incident_number, location, keyword, caller_name, caller_contact, started_at, status, notes, created_by, created_at, location_x, location_y) 
                    VALUES (:incident_number, :location, :keyword, :caller_name, :caller_contact, :started_at, 'in_sichtung', :notes, NULL, :created_at, :location_x, :location_y)
                ");
                $insertFireIncidentStmt->execute([
                    ':incident_number' => (string)$dispatchId,
                    ':location' => $location,
                    ':keyword' => $keyword,
                    ':caller_name' => $callerName ?: null,
                    ':caller_contact' => $callerContact ?: null,
                    ':started_at' => $currentDateTime,
                    ':notes' => $notes,
                    ':created_at' => $currentDateTime,
                    ':location_x' => $locationX,
                    ':location_y' => $locationY
                ]);

                $fireIncidentId = (int)$pdo->lastInsertId();
                logSync("Fire Incident #$fireIncidentId für Dispatch #$dispatchId erstellt (Location: $location, Keyword: $keyword)", 'INFO');

                // Füge alle Feuerwehrfahrzeuge hinzu
                foreach ($fireVehicles as $fireVehicle) {
                    // Hole die vehicle_id aus der Datenbank
                    $getVehicleIdStmt = $pdo->prepare("
                        SELECT id FROM intra_fahrzeuge 
                        WHERE identifier = :identifier 
                        LIMIT 1
                    ");
                    $getVehicleIdStmt->execute([':identifier' => $fireVehicle['identifier']]);
                    $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

                    if ($vehicleIdRow) {
                        $vehicleId = (int)$vehicleIdRow['id'];

                        // Prüfe ob Fahrzeug bereits zugeordnet ist
                        $checkVehicleAssignmentStmt = $pdo->prepare("
                            SELECT id FROM intra_fire_incident_vehicles 
                            WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                            LIMIT 1
                        ");
                        $checkVehicleAssignmentStmt->execute([
                            ':incident_id' => $fireIncidentId,
                            ':vehicle_id' => $vehicleId
                        ]);

                        if (!$checkVehicleAssignmentStmt->fetch()) {
                            $insertVehicleStmt = $pdo->prepare("
                                INSERT INTO intra_fire_incident_vehicles 
                                (incident_id, vehicle_id, from_other_org, created_by, created_at) 
                                VALUES (:incident_id, :vehicle_id, 0, NULL, NOW())
                            ");
                            $insertVehicleStmt->execute([
                                ':incident_id' => $fireIncidentId,
                                ':vehicle_id' => $vehicleId
                            ]);
                            logSync("Fahrzeug {$fireVehicle['name']} (ID: $vehicleId) zu Fire Incident #$fireIncidentId hinzugefügt", 'INFO');
                        }
                    }
                }

                // Erstelle Log-Eintrag mit System als Benutzer (created_by = NULL)
                $insertLogStmt = $pdo->prepare("
                    INSERT INTO intra_fire_incident_log 
                    (incident_id, action_type, action_description, vehicle_id, operator_id, created_by, created_at) 
                    VALUES (:incident_id, 'created', :action_description, NULL, NULL, NULL, NOW())
                ");
                $insertLogStmt->execute([
                    ':incident_id' => $fireIncidentId,
                    ':action_description' => 'Einsatz automatisch durch Sync erstellt'
                ]);

                $createdFireIncidents++;
                $fireIncidentsByDispatch[$dispatchId] = $fireIncidentId;
                logSync("Fire Incident #$fireIncidentId erfolgreich erstellt mit " . count($fireVehicles) . " Fahrzeugen", 'INFO');
            } else {
                $fireIncidentId = (int)$existingFireIncident['id'];
                $fireIncidentsByDispatch[$dispatchId] = $fireIncidentId;
                logSync("Fire Incident #$fireIncidentId existiert bereits für Dispatch #$dispatchId", 'INFO');

                // Füge neue Fahrzeuge hinzu, falls noch nicht vorhanden
                foreach ($fireVehicles as $fireVehicle) {
                    $getVehicleIdStmt = $pdo->prepare("
                        SELECT id FROM intra_fahrzeuge 
                        WHERE identifier = :identifier 
                        LIMIT 1
                    ");
                    $getVehicleIdStmt->execute([':identifier' => $fireVehicle['identifier']]);
                    $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

                    if ($vehicleIdRow) {
                        $vehicleId = (int)$vehicleIdRow['id'];

                        $checkVehicleAssignmentStmt = $pdo->prepare("
                            SELECT id FROM intra_fire_incident_vehicles 
                            WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                            LIMIT 1
                        ");
                        $checkVehicleAssignmentStmt->execute([
                            ':incident_id' => $fireIncidentId,
                            ':vehicle_id' => $vehicleId
                        ]);

                        if (!$checkVehicleAssignmentStmt->fetch()) {
                            $insertVehicleStmt = $pdo->prepare("
                                INSERT INTO intra_fire_incident_vehicles 
                                (incident_id, vehicle_id, from_other_org, created_by, created_at) 
                                VALUES (:incident_id, :vehicle_id, 0, NULL, NOW())
                            ");
                            $insertVehicleStmt->execute([
                                ':incident_id' => $fireIncidentId,
                                ':vehicle_id' => $vehicleId
                            ]);

                            // Log-Eintrag für hinzugefügtes Fahrzeug
                            $insertLogStmt = $pdo->prepare("
                                INSERT INTO intra_fire_incident_log 
                                (incident_id, action_type, action_description, vehicle_id, operator_id, created_by, created_at) 
                                VALUES (:incident_id, 'vehicle_added', :action_description, :vehicle_id, NULL, NULL, NOW())
                            ");
                            $insertLogStmt->execute([
                                ':incident_id' => $fireIncidentId,
                                ':vehicle_id' => $vehicleId,
                                ':action_description' => "Fahrzeug {$fireVehicle['name']} durch Sync hinzugefügt"
                            ]);

                            logSync("Fahrzeug {$fireVehicle['name']} (ID: $vehicleId) zu bestehendem Fire Incident #$fireIncidentId hinzugefügt", 'INFO');
                        }
                    }
                }
            }
        }

        // Verarbeite Lagemeldungen aus dispatch_data (für Fire Incidents)
        $dispatchData = $dispatchDataByDispatch[$dispatchId] ?? null;
        if ($dispatchData && isset($dispatchData['lagemeldungen']) && is_array($dispatchData['lagemeldungen']) && isset($fireIncidentsByDispatch[$dispatchId])) {
            $currentFireIncidentId = $fireIncidentsByDispatch[$dispatchId];
            $sitrepsToProcess = array_filter($dispatchData['lagemeldungen'], function ($entry) {
                return isset($entry['type']) && in_array($entry['type'], [
                    'control_dispatch_form_entry_type_situation_report',
                    'control_dispatch_form_entry_type_situation_report_important'
                ]);
            });

            if (!empty($sitrepsToProcess)) {
                logSync("Verarbeite " . count($sitrepsToProcess) . " Lagemeldungen von Leitstelle für Fire Incident #$currentFireIncidentId", 'INFO');

                foreach ($sitrepsToProcess as $sitrep) {
                    $sitrepText = trim($sitrep['text'] ?? '');
                    $sitrepDate = $sitrep['date'] ?? '';
                    $sitrepTime = $sitrep['time'] ?? '';
                    $sitrepSender = $sitrep['sender'] ?? 'Leitstelle';

                    if (empty($sitrepText) || empty($sitrepDate) || empty($sitrepTime)) {
                        logSync("Lagemeldung übersprungen (fehlende Daten): text='$sitrepText', date='$sitrepDate', time='$sitrepTime'", 'WARNING');
                        continue;
                    }

                    // Parse report_time aus date (dd.mm.YYYY) + time (HH:mm) als Berlin-Zeit, dann nach UTC konvertieren
                    // fmt_dt() interpretiert gespeicherte Zeiten als UTC und konvertiert nach Europe/Berlin
                    $reportTime = DateTime::createFromFormat('d.m.Y H:i', $sitrepDate . ' ' . $sitrepTime, new DateTimeZone('Europe/Berlin'));
                    if (!$reportTime) {
                        logSync("Ungültiges Zeitformat für Lagemeldung: date='$sitrepDate', time='$sitrepTime'", 'WARNING');
                        continue;
                    }
                    $reportTime->setTimezone(new DateTimeZone('UTC'));
                    $reportTimeFormatted = $reportTime->format('Y-m-d H:i:s');

                    // Deduplizierung: Prüfe ob gleicher Text für diesen Einsatz innerhalb ±5 Minuten existiert
                    // Zeitstempel können sich leicht unterscheiden wenn lokale Meldungen über FiveM zurückgespiegelt werden
                    $checkDuplicateStmt = $pdo->prepare("
                        SELECT id FROM intra_fire_incident_sitreps
                        WHERE incident_id = :incident_id
                        AND text = :text
                        AND ABS(TIMESTAMPDIFF(SECOND, report_time, :report_time)) <= 300
                        LIMIT 1
                    ");
                    $checkDuplicateStmt->execute([
                        ':incident_id' => $currentFireIncidentId,
                        ':text' => $sitrepText,
                        ':report_time' => $reportTimeFormatted
                    ]);

                    if ($checkDuplicateStmt->fetch()) {
                        logSync("Lagemeldung bereits vorhanden (Duplikat): '$sitrepText' um $reportTimeFormatted", 'DEBUG');
                        continue;
                    }

                    // Ersteller-Anzeige: "Leitstelle (Sender)" wenn Sender bekannt
                    $radioName = 'Leitstelle';

                    // INSERT neue Lagemeldung
                    $insertSitrepStmt = $pdo->prepare("
                        INSERT INTO intra_fire_incident_sitreps
                        (incident_id, report_time, text, vehicle_radio_name, vehicle_id, created_by, source, synced)
                        VALUES (:incident_id, :report_time, :text, :radio_name, NULL, NULL, 'leitstelle', 1)
                    ");
                    $insertSitrepStmt->execute([
                        ':incident_id' => $currentFireIncidentId,
                        ':report_time' => $reportTimeFormatted,
                        ':text' => $sitrepText,
                        ':radio_name' => $radioName
                    ]);

                    $newSitrepsFromDispatch++;
                    logSync("Lagemeldung von Leitstelle hinzugefügt: '$sitrepText' (Sender: $sitrepSender, Zeit: $reportTimeFormatted) für Fire Incident #$currentFireIncidentId", 'INFO');
                }
            }
        }

        $checkExistingStmt = $pdo->prepare("
            SELECT enr
            FROM intra_edivi
            WHERE enr = :enr OR enr LIKE :enr_pattern
        ");
        $checkExistingStmt->execute([
            ':enr' => $dispatchId,
            ':enr_pattern' => $dispatchId . '_%'
        ]);
        $existingEnrs = $checkExistingStmt->fetchAll(PDO::FETCH_COLUMN);

        logSync("Bereits vorhandene ENRs für Einsatz #$dispatchId: " . implode(', ', $existingEnrs), 'DEBUG');

        foreach ($validVehicles as $vehicle) {
            $vehicleIdentifier = $vehicle['identifier'];
            $fieldToCheck = $vehicle['is_notarzt'] ? 'fzg_na' : 'fzg_transp';

            $checkVehicleStmt = $pdo->prepare("
                SELECT enr
                FROM intra_edivi 
                WHERE (enr = :enr OR enr LIKE :enr_pattern)
                AND $fieldToCheck = :vehicle_id
                LIMIT 1
            ");
            $checkVehicleStmt->execute([
                ':enr' => $dispatchId,
                ':enr_pattern' => $dispatchId . '_%',
                ':vehicle_id' => $vehicleIdentifier
            ]);
            $existingEntry = $checkVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingEntry) {
                logSync("Fahrzeug '$vehicleIdentifier' existiert bereits in Einsatz {$existingEntry['enr']}, wird übersprungen", 'DEBUG');
                continue;
            }

            $enrToUse = null;

            if (!in_array((string)$dispatchId, $existingEnrs)) {
                $enrToUse = $dispatchId;
            } else {
                $suffix = 1;
                while (true) {
                    $testEnr = $dispatchId . '_' . $suffix;
                    if (!in_array($testEnr, $existingEnrs)) {
                        $enrToUse = $testEnr;
                        break;
                    }
                    $suffix++;
                }
            }

            if ($vehicle['is_notarzt']) {
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                // Extrahiere Patientendaten aus dispatch_data (falls vorhanden)
                $dispatchData = $dispatchDataByDispatch[$dispatchId] ?? null;
                $patientName = null;
                $patientVorname = null;
                $patientNachname = null;
                $patientBirthdate = null;

                if ($dispatchData && isset($dispatchData['patienten']) && is_array($dispatchData['patienten']) && !empty($dispatchData['patienten'])) {
                    // Nehme ersten Patienten
                    $patient = $dispatchData['patienten'][0];

                    // Formatiere Name als "Nachname, Vorname"
                    if (isset($patient['nachname']) || isset($patient['vorname'])) {
                        $patientNachname = !empty($patient['nachname']) ? $patient['nachname'] : null;
                        $patientVorname = !empty($patient['vorname']) ? $patient['vorname'] : null;

                        if ($patientNachname && $patientVorname) {
                            $patientName = $patientNachname . ', ' . $patientVorname;
                        } elseif ($patientNachname) {
                            $patientName = $patientNachname;
                        } elseif ($patientVorname) {
                            $patientName = $patientVorname;
                        }
                    }

                    // Berechne Geburtsdatum basierend auf Alter (01.01.XXXX)
                    if (isset($patient['alter']) && is_numeric($patient['alter'])) {
                        $alter = intval($patient['alter']);
                        $currentYear = intval(date('Y'));
                        $birthYear = $currentYear - $alter;
                        $patientBirthdate = $birthYear . '-01-01';
                    }

                    if ($patientName || $patientBirthdate) {
                        logSync("Patientendaten für Einsatz #$dispatchId gefunden: Name='$patientName', Geburtsdatum='$patientBirthdate'", 'INFO');
                    }
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_na, edatum, ezeit, prot_by, patname, pat_vorname, pat_nachname, patgebdat, created_at, createdby)
                    VALUES (:enr, :fzg_na, :edatum, :ezeit, :prot_by, :patname, :pat_vorname, :pat_nachname, :patgebdat, NOW(), 1)
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_na' => $vehicle['identifier'],
                    ':edatum' => $currentDate,
                    ':ezeit' => $currentTime,
                    ':prot_by' => 1,
                    ':patname' => $patientName,
                    ':pat_vorname' => $patientVorname,
                    ':pat_nachname' => $patientNachname,
                    ':patgebdat' => $patientBirthdate
                ]);

                logSync("Notarzt-Eintrag $enrToUse erstellt: {$vehicle['identifier']}", 'INFO');
            } else {
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                // Extrahiere Patientendaten aus dispatch_data (falls vorhanden)
                $dispatchData = $dispatchDataByDispatch[$dispatchId] ?? null;
                $patientName = null;
                $patientVorname = null;
                $patientNachname = null;
                $patientBirthdate = null;

                if ($dispatchData && isset($dispatchData['patienten']) && is_array($dispatchData['patienten']) && !empty($dispatchData['patienten'])) {
                    // Nehme ersten Patienten
                    $patient = $dispatchData['patienten'][0];

                    // Formatiere Name als "Nachname, Vorname"
                    if (isset($patient['nachname']) || isset($patient['vorname'])) {
                        $patientNachname = !empty($patient['nachname']) ? $patient['nachname'] : null;
                        $patientVorname = !empty($patient['vorname']) ? $patient['vorname'] : null;

                        if ($patientNachname && $patientVorname) {
                            $patientName = $patientNachname . ', ' . $patientVorname;
                        } elseif ($patientNachname) {
                            $patientName = $patientNachname;
                        } elseif ($patientVorname) {
                            $patientName = $patientVorname;
                        }
                    }

                    // Berechne Geburtsdatum basierend auf Alter (01.01.XXXX)
                    if (isset($patient['alter']) && is_numeric($patient['alter'])) {
                        $alter = intval($patient['alter']);
                        $currentYear = intval(date('Y'));
                        $birthYear = $currentYear - $alter;
                        $patientBirthdate = $birthYear . '-01-01';
                    }

                    if ($patientName || $patientBirthdate) {
                        logSync("Patientendaten für Einsatz #$dispatchId gefunden: Name='$patientName', Geburtsdatum='$patientBirthdate'", 'INFO');
                    }
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_transp, edatum, ezeit, prot_by, patname, pat_vorname, pat_nachname, patgebdat, created_at, createdby)
                    VALUES (:enr, :fzg_transp, :edatum, :ezeit, :prot_by, :patname, :pat_vorname, :pat_nachname, :patgebdat, NOW(), 1)
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_transp' => $vehicle['identifier'],
                    ':edatum' => $currentDate,
                    ':ezeit' => $currentTime,
                    ':prot_by' => 0,
                    ':patname' => $patientName,
                    ':pat_vorname' => $patientVorname,
                    ':pat_nachname' => $patientNachname,
                    ':patgebdat' => $patientBirthdate
                ]);

                logSync("Transport-Eintrag $enrToUse erstellt: {$vehicle['identifier']}", 'INFO');
            }

            $existingEnrs[] = $enrToUse;
            $createdEntries++;
        }

        $processedDispatches++;
    }

    // V2: Verarbeite top-level Lagemeldungen (falls separat von dispatch_data gesendet)
    if ($isV2 && isset($receivedData['lagemeldungen']) && is_array($receivedData['lagemeldungen'])) {
        foreach ($receivedData['lagemeldungen'] as $lageDId => $lageEntries) {
            if (!isset($fireIncidentsByDispatch[$lageDId]) || !is_array($lageEntries)) continue;
            $currentFireIncidentId = $fireIncidentsByDispatch[$lageDId];

            $sitrepsToProcess = array_filter($lageEntries, function ($entry) {
                return isset($entry['type']) && in_array($entry['type'], [
                    'control_dispatch_form_entry_type_situation_report',
                    'control_dispatch_form_entry_type_situation_report_important'
                ]);
            });

            foreach ($sitrepsToProcess as $sitrep) {
                $sitrepText = trim($sitrep['text'] ?? '');
                $sitrepDate = $sitrep['date'] ?? '';
                $sitrepTime = $sitrep['time'] ?? '';

                if (empty($sitrepText) || empty($sitrepDate) || empty($sitrepTime)) continue;

                $reportTime = DateTime::createFromFormat('d.m.Y H:i', $sitrepDate . ' ' . $sitrepTime, new DateTimeZone('Europe/Berlin'));
                if (!$reportTime) continue;
                $reportTime->setTimezone(new DateTimeZone('UTC'));
                $reportTimeFormatted = $reportTime->format('Y-m-d H:i:s');

                $checkDuplicateStmt = $pdo->prepare("
                    SELECT id FROM intra_fire_incident_sitreps
                    WHERE incident_id = :incident_id AND text = :text
                    AND ABS(TIMESTAMPDIFF(SECOND, report_time, :report_time)) <= 300
                    LIMIT 1
                ");
                $checkDuplicateStmt->execute([
                    ':incident_id' => $currentFireIncidentId,
                    ':text' => $sitrepText,
                    ':report_time' => $reportTimeFormatted
                ]);
                if ($checkDuplicateStmt->fetch()) continue;

                $insertSitrepStmt = $pdo->prepare("
                    INSERT INTO intra_fire_incident_sitreps
                    (incident_id, report_time, text, vehicle_radio_name, vehicle_id, created_by, source, synced)
                    VALUES (:incident_id, :report_time, :text, 'Leitstelle', NULL, NULL, 'leitstelle', 1)
                ");
                $insertSitrepStmt->execute([
                    ':incident_id' => $currentFireIncidentId,
                    ':report_time' => $reportTimeFormatted,
                    ':text' => $sitrepText
                ]);
                $newSitrepsFromDispatch++;
                logSync("V2 Lagemeldung: '$sitrepText' für Fire Incident #$currentFireIncidentId", 'INFO');
            }
        }
    }

    // Sammle lokale Lagemeldungen (noch nicht gesynct) für die Response
    $situationReports = [];
    $sitrepIdsToMarkSynced = [];

    foreach ($fireIncidentsByDispatch as $dId => $fIncidentId) {
        $localSitrepsStmt = $pdo->prepare("
            SELECT s.id, s.report_time, s.text, s.vehicle_radio_name, f.name AS sys_name
            FROM intra_fire_incident_sitreps s
            LEFT JOIN intra_fahrzeuge f ON s.vehicle_id = f.id
            WHERE s.incident_id = :incident_id
            AND (s.source IS NULL OR s.source != 'leitstelle')
            AND s.synced = 0
            ORDER BY s.report_time ASC
        ");
        $localSitrepsStmt->execute([':incident_id' => $fIncidentId]);
        $localSitreps = $localSitrepsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($localSitreps)) {
            $situationReports[(string)$dId] = [];
            foreach ($localSitreps as $ls) {
                $reportDt = DateTime::createFromFormat('Y-m-d H:i:s', $ls['report_time']);
                $situationReports[(string)$dId][] = [
                    'text' => $ls['text'],
                    'time' => $reportDt ? $reportDt->format('H:i') : '',
                    'date' => $reportDt ? $reportDt->format('d.m.Y') : '',
                    'sender' => $ls['vehicle_radio_name'] ?? $ls['sys_name'] ?? 'Unbekannt'
                ];
                $sitrepIdsToMarkSynced[] = (int)$ls['id'];
            }
            logSync("Sende " . count($localSitreps) . " lokale Lagemeldungen für Dispatch #$dId an Leitstelle zurück", 'INFO');
        }
    }

    // Markiere gesendete Lagemeldungen als synced
    if (!empty($sitrepIdsToMarkSynced)) {
        $placeholders = implode(',', array_fill(0, count($sitrepIdsToMarkSynced), '?'));
        $markSyncedStmt = $pdo->prepare("UPDATE intra_fire_incident_sitreps SET synced = 1 WHERE id IN ($placeholders)");
        $markSyncedStmt->execute($sitrepIdsToMarkSynced);
        logSync(count($sitrepIdsToMarkSynced) . " lokale Lagemeldungen als synced markiert", 'INFO');
    }

    // Sammle Patientendaten die zum Senden markiert wurden (pat_synced = 2)
    $patientUpdates = [];
    $patientEnrsToMarkSynced = [];

    $patSyncStmt = $pdo->prepare("
        SELECT enr, pat_vorname, pat_nachname, patgebdat, prot_by, fzg_na, fzg_transp, ziel_poi
        FROM intra_edivi
        WHERE pat_synced = 2
    ");
    $patSyncStmt->execute();
    $pendingPatients = $patSyncStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pendingPatients as $pp) {
        $patAge = null;
        if (!empty($pp['patgebdat'])) {
            $birthDate = new DateTime($pp['patgebdat']);
            $now = new DateTime();
            $patAge = $now->diff($birthDate)->y;
        }

        // Funkrufname: fzg_transp bevorzugt, fzg_na als Fallback
        $funkrufname = null;
        $vehicleIdentifier = !empty($pp['fzg_transp']) ? $pp['fzg_transp'] : ($pp['fzg_na'] ?? null);
        if (!empty($vehicleIdentifier)) {
            $vehNameStmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :identifier LIMIT 1");
            $vehNameStmt->execute([':identifier' => $vehicleIdentifier]);
            $funkrufname = $vehNameStmt->fetchColumn() ?: null;
        }

        $patientUpdates[(string)$pp['enr']] = [
            'vorname' => !empty($pp['pat_vorname']) ? $pp['pat_vorname'] : 'Unbekannt',
            'nachname' => !empty($pp['pat_nachname']) ? $pp['pat_nachname'] : 'Unbekannt',
            'alter' => $patAge,
            'funkrufname' => $funkrufname,
            'transportziel' => !empty($pp['ziel_poi']) ? $pp['ziel_poi'] : null
        ];
        $patientEnrsToMarkSynced[] = $pp['enr'];
    }

    if (!empty($patientEnrsToMarkSynced)) {
        $placeholders = implode(',', array_fill(0, count($patientEnrsToMarkSynced), '?'));
        $pdo->prepare("UPDATE intra_edivi SET pat_synced = 1 WHERE enr IN ($placeholders)")->execute($patientEnrsToMarkSynced);
        logSync(count($patientEnrsToMarkSynced) . " Patientendaten als synced markiert", 'INFO');
    }

    // Sammle ausstehende Status-Änderungen (ersetzt separaten emd-status-poll.php)
    $statusChanges = [];
    $statusQueueStmt = $pdo->prepare("
        SELECT id, vehicle_name, new_status, incident_number, created_at
        FROM intra_fire_status_queue
        WHERE delivered = 0
        ORDER BY created_at ASC
    ");
    $statusQueueStmt->execute();
    $pendingStatuses = $statusQueueStmt->fetchAll(PDO::FETCH_ASSOC);

    logSync("Status-Queue: " . count($pendingStatuses) . " ausstehende Einträge gefunden", 'DEBUG');

    if (!empty($pendingStatuses)) {
        $statusIdsToDeliver = [];
        foreach ($pendingStatuses as $sq) {
            $statusChanges[] = [
                'vehicle_name' => $sq['vehicle_name'],
                'status' => $sq['new_status'],
                'incident_number' => $sq['incident_number'],
                'timestamp' => (new DateTime($sq['created_at']))->format('d.m.Y H:i')
            ];
            $statusIdsToDeliver[] = (int)$sq['id'];
        }

        $placeholders = implode(',', array_fill(0, count($statusIdsToDeliver), '?'));
        $pdo->prepare("UPDATE intra_fire_status_queue SET delivered = 1 WHERE id IN ($placeholders)")->execute($statusIdsToDeliver);
        logSync(count($statusIdsToDeliver) . " Status-Änderungen als delivered markiert", 'INFO');
    }

    $pdo->commit();

    logSync("Synchronisation abgeschlossen: Einsätze=$processedDispatches, Einträge erstellt=$createdEntries, Übersprungen=$skippedDispatches, Fire Incidents=$createdFireIncidents, Neue Lagemeldungen=$newSitrepsFromDispatch", 'INFO');

    $response = [
        'success' => true,
        'message' => 'Synchronisation erfolgreich abgeschlossen',
        'statistics' => [
            'total_vehicles' => count($vehicles),
            'unique_dispatches' => count($vehiclesByDispatch),
            'processed_dispatches' => $processedDispatches,
            'created_entries' => $createdEntries,
            'skipped_dispatches' => $skippedDispatches,
            'created_fire_incidents' => $createdFireIncidents,
            'new_sitreps_from_dispatch' => $newSitrepsFromDispatch
        ]
    ];

    if (!empty($situationReports)) {
        $response['situation_reports'] = $situationReports;
    }

    if (!empty($patientUpdates)) {
        $response['patient_updates'] = $patientUpdates;
    }

    if (!empty($statusChanges)) {
        $response['status_changes'] = $statusChanges;
    }

    // V2: Zusätzliche Response-Felder
    if ($isV2) {
        $response['status_poll'] = ['status_changes' => $statusChanges];
        $response['status_ack'] = ['successful_ids' => $v2StatusResult['successful_ids']];
    }

    echo json_encode($response);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logSync('Datenbankfehler: ' . $e->getMessage(), 'ERROR');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logSync('Fehler: ' . $e->getMessage(), 'ERROR');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Interner Fehler',
        'message' => $e->getMessage()
    ]);
}
