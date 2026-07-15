<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;
use PDOException;

/**
 * EMD-Sync-Controller — der zentrale FiveM-Server-Endpoint.
 *
 * Business-Logik ist 1:1 übernommen, nur strukturell umgebaut:
 *
 *   - Alle `logSync(...)` → `Logger::*(...)` (landet im zentralen
 *     Monolog-Logfile statt in `api/emd/logs/emd_sync.log`)
 *   - Alle `echo json_encode(...); exit;` → `return Response::json(...)`
 *   - `$pdo` → `$this->pdo` (via Constructor-Injection)
 *   - Hilfsfunktionen → private Methoden auf dem Controller
 *
 * Auth läuft jetzt ausschließlich via `ApiKeyMiddleware` am Router-
 * Eingang — der Controller selbst macht keinen API-Key-Check mehr.
 *
 * ======================================================================
 * WICHTIG: Dieser Controller enthält bewusst den FW-Fahrzeug-Bug-Fix
 * (rd_type=3 Fahrzeuge werden in $fireVehicles statt $validVehicles
 * sortiert). Änderungen an dem Bereich müssen den Mixed-Dispatch-Fall
 * (RTW + NEF + LHF) weiterhin korrekt behandeln.
 * ======================================================================
 */
final class EmdSyncController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Public Entry ─────────────────────────────────────────────────

    /**
     * POST /api/emd/sync
     */
    public function sync(Request $request): Response
    {
        $receivedData = $request->json();
        if (!is_array($receivedData)) {
            Logger::error('EmdSync: Ungültiges JSON empfangen');
            return Response::json(['success' => false, 'error' => 'Ungültiges JSON'], 400);
        }

        // Letzten Sync-Zeitpunkt speichern (für Verbindungs-Status in der Topbar)
        @file_put_contents(
            dirname(__DIR__, 4) . '/storage/last_emd_sync.txt',
            date('Y-m-d H:i:s')
        );

        try {
            // ── Vehicle Registry (optional, kann mit oder ohne dispatch kommen) ──
            $vehicleRegistryResult = null;
            if (isset($receivedData['vehicle_registry']) && is_array($receivedData['vehicle_registry'])) {
                $vehicleRegistryResult = $this->importVehicleRegistry($receivedData['vehicle_registry']);

                // Wenn NUR vehicle_registry gesendet wurde, direkt antworten
                if (!isset($receivedData['type']) && !isset($receivedData['dispatch_data']) && !isset($receivedData['data'])) {
                    return Response::json([
                        'success'                   => true,
                        'message'                   => "Vehicle Registry empfangen: {$vehicleRegistryResult} Fahrzeuge zur Prüfung bereit",
                        'vehicle_registry_received' => $vehicleRegistryResult,
                    ]);
                }
            }

            // ── V1: Type-basiertes Routing ──
            if (isset($receivedData['type'])) {
                return match ($receivedData['type']) {
                    'dispatch_logs'      => $this->handleDispatchLogs($receivedData),
                    'status_updates'     => $this->handleStatusUpdates($receivedData),
                    'status_no_dispatch' => $this->handleStatusNoDispatch($receivedData),
                    default              => $this->unknownType($receivedData['type']),
                };
            }

            // ── V2: Unified Request ──
            return $this->handleUnifiedSync($receivedData);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('EmdSync: Datenbankfehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'error'   => 'Datenbankfehler',
                'message' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('EmdSync: Interner Fehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'error'   => 'Interner Fehler',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Vehicle Registry ─────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $vehicles
     * @return int  Anzahl importierter Fahrzeuge
     */
    private function importVehicleRegistry(array $vehicles): int
    {
        Logger::info('EmdSync: Vehicle Registry empfangen', ['count' => count($vehicles)]);

        // Alte pending-Einträge entfernen (neuer Import ersetzt alten)
        $this->pdo->exec("DELETE FROM intra_fahrzeuge_import_queue WHERE status = 'pending'");

        $insertStmt = $this->pdo->prepare("
            INSERT INTO intra_fahrzeuge_import_queue
                (emd_vehicle_id, name, identifier, veh_type, rd_type, department, valuelong, job, image, funkkanal, raw_data)
            VALUES
                (:emd_id, :name, :identifier, :veh_type, :rd_type, :department, :valuelong, :job, :image, :funkkanal, :raw_data)
        ");

        $imported = 0;
        foreach ($vehicles as $v) {
            $vName = $v['value'] ?? '';
            if (empty($vName)) {
                continue;
            }

            // rd_type aus Fahrzeugtyp ableiten
            $rdType = 0;
            $vType = strtoupper((string) ($v['type'] ?? ''));
            if (in_array($vType, ['NEF', 'NAW', 'ITW', 'RTH', 'ITH'], true)) {
                $rdType = 1; // RD mit NA (arztbesetzt)
            } elseif (in_array($vType, ['RTW', 'KTW', 'NTW', 'N-KTW', 'NKTW', 'S-RTW'], true)) {
                $rdType = 2; // RD ohne NA
            }

            // Identifier aus value generieren (lowercase, Leerzeichen → Unterstrich)
            $identifier = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $vName));

            $insertStmt->execute([
                ':emd_id'     => $v['id'] ?? null,
                ':name'       => $vName,
                ':identifier' => $identifier,
                ':veh_type'   => $v['type'] ?? '',
                ':rd_type'    => $rdType,
                ':department' => $v['department'] ?? null,
                ':valuelong'  => $v['valuelong'] ?? null,
                ':job'        => $v['job'] ?? null,
                ':image'      => $v['image'] ?? null,
                ':funkkanal'  => $v['funkkanal'] ?? null,
                ':raw_data'   => json_encode($v),
            ]);
            $imported++;
        }

        // Flag-Datei löschen
        $flagFile = dirname(__DIR__, 4) . '/storage/emd_vehicle_import_request.flag';
        if (file_exists($flagFile)) {
            @unlink($flagFile);
        }

        Logger::info('EmdSync: Vehicle Registry importiert', ['imported' => $imported]);

        return $imported;
    }

    // ── V1: Type-basierte Handler ────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleDispatchLogs(array $data): Response
    {
        Logger::info('EmdSync: Dispatch-Log-Sync empfangen (keine Speicherung erforderlich)');

        $missions = $data['missions'] ?? [];
        if (empty($missions)) {
            Logger::info('EmdSync: Keine Einsätze in der Anfrage');
            return Response::json(['success' => true, 'message' => 'Keine Einsätze zu verarbeiten', 'processed' => 0]);
        }

        Logger::info('EmdSync: Dispatch-Logs empfangen', ['count' => count($missions)]);

        return Response::json([
            'success'        => true,
            'type'           => 'dispatch_logs',
            'processed'      => count($missions),
            'total_received' => count($missions),
            'message'        => 'Dispatch-Logs empfangen, Statusmeldungen werden über Status-Sync verarbeitet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleStatusUpdates(array $data): Response
    {
        Logger::info('EmdSync: Starte Status-Update-Verarbeitung');

        $statuses = $data['statuses'] ?? [];
        if (empty($statuses)) {
            return Response::json([
                'success'   => true,
                'message'   => 'Keine Statusmeldungen zu verarbeiten',
                'processed' => 0,
            ]);
        }

        Logger::info('EmdSync: Statusmeldungen empfangen', ['count' => count($statuses)]);

        $result = $this->processStatusUpdatesInternal($statuses);

        Logger::info('EmdSync: Status-Update-Verarbeitung abgeschlossen', [
            'updated'   => $result['updated'],
            'not_found' => $result['not_found'],
            'total'     => $result['total'],
        ]);

        return Response::json([
            'success'        => true,
            'type'           => 'status_updates',
            'updated_edivi'  => $result['updated'],
            'not_found'      => $result['not_found'],
            'successful_ids' => $result['successful_ids'],
            'total_received' => $result['total'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleStatusNoDispatch(array $data): Response
    {
        $vehicleName = $data['vehicle_name'] ?? '';
        $newStatus   = $data['status'] ?? '';
        $statusTime  = $data['time'] ?? '';
        $statusDate  = $data['date'] ?? '';

        if (empty($vehicleName) || $newStatus === '') {
            Logger::warning('EmdSync: status_no_dispatch fehlende Daten', [
                'vehicle_name' => $vehicleName,
                'status'       => $newStatus,
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Fehlende Pflichtfelder (vehicle_name, status)',
            ], 400);
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

        $findVehicleStmt = $this->pdo->prepare("SELECT id, name FROM intra_fahrzeuge WHERE name = :name LIMIT 1");
        $findVehicleStmt->execute([':name' => $vehicleName]);
        $vehicle = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            Logger::warning('EmdSync: status_no_dispatch Fahrzeug nicht gefunden', ['vehicle_name' => $vehicleName]);
            return Response::json(['success' => false, 'error' => 'Fahrzeug nicht gefunden'], 404);
        }

        $vehicleId = (int) $vehicle['id'];

        $updateStmt = $this->pdo->prepare("
            UPDATE intra_fahrzeuge
            SET current_status = :status, status_updated_at = :updated_at, status_source = 'no_dispatch'
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':status'     => (string) $newStatus,
            ':updated_at' => $updatedAt,
            ':id'         => $vehicleId,
        ]);

        Logger::info('EmdSync: status_no_dispatch Update', [
            'vehicle_name' => $vehicleName,
            'vehicle_id'   => $vehicleId,
            'new_status'   => $newStatus,
            'time'         => $updatedAt,
        ]);

        return Response::json([
            'success'    => true,
            'vehicle_id' => $vehicleId,
            'new_status' => $newStatus,
            'source'     => 'no_dispatch',
        ]);
    }

    private function unknownType(string $type): Response
    {
        Logger::warning('EmdSync: Unbekannter Sync-Typ', ['type' => $type]);
        return Response::json(['success' => false, 'error' => 'Unbekannter Sync-Typ'], 400);
    }

    /**
     * Interne Status-Update-Verarbeitung, gemeinsam genutzt von V1-Handler
     * und V2-Unified-Request.
     *
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array{updated:int, not_found:int, successful_ids:array<int, int|string>, total:int}
     */
    private function processStatusUpdatesInternal(array $statuses): array
    {
        $updated       = 0;
        $notFound      = 0;
        $successfulIds = [];

        foreach ($statuses as $status) {
            $statusValue   = $status['status'];
            $missionNumber = $status['mission_number'];
            $sender        = $status['sender'];
            $statusId      = $status['id'];

            $statusTime = DateTime::createFromFormat('d.m.Y H:i', $status['timestamp']);
            if (!$statusTime) {
                Logger::warning('EmdSync: Ungültiges Zeitformat', ['status_id' => $statusId]);
                continue;
            }

            $statusColumn = 's' . $statusValue;

            $findVehicleStmt = $this->pdo->prepare("
                SELECT identifier, rd_type
                FROM intra_fahrzeuge
                WHERE name = :name
                LIMIT 1
            ");
            $findVehicleStmt->execute([':name' => $sender]);
            $vehicleRow = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$vehicleRow) {
                $notFound++;
                Logger::warning('EmdSync: Fahrzeug nicht gefunden', ['sender' => $sender, 'status_id' => $statusId]);
                continue;
            }

            $vehicleIdentifier = $vehicleRow['identifier'];
            $rdType            = (int) ($vehicleRow['rd_type'] ?? 0);

            // Status-Zuordnung läuft über intra_edivi (eNOTF-Plugin) — ohne
            // aktives Plugin gibt es keine Protokolle, denen der Status
            // zugeordnet werden könnte.
            if (!$this->enotfActive()) {
                $notFound++;
                continue;
            }

            $findEnrStmt = $this->pdo->prepare("
                SELECT enr
                FROM intra_edivi
                WHERE (enr = :mission OR enr LIKE :mission_pattern)
                  AND (fzg_na = :vehicle_id1 OR fzg_transp = :vehicle_id2)
                LIMIT 1
            ");
            $findEnrStmt->execute([
                ':mission'         => $missionNumber,
                ':mission_pattern' => $missionNumber . '_%',
                ':vehicle_id1'     => $vehicleIdentifier,
                ':vehicle_id2'     => $vehicleIdentifier,
            ]);
            $enrRow = $findEnrStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrRow) {
                $notFound++;
                Logger::warning('EmdSync: Einsatz nicht gefunden', [
                    'identifier' => $vehicleIdentifier,
                    'mission'    => $missionNumber,
                    'status_id'  => $statusId,
                ]);
                continue;
            }

            $enr           = $enrRow['enr'];
            $formattedTime = $statusTime->format('Y-m-d H:i:s');

            if (strtoupper(trim((string) $statusValue)) === 'C') {
                $sql        = "UPDATE intra_edivi SET salarm = :salarm WHERE enr = :enr";
                $updateStmt = $this->pdo->prepare($sql);
                $updateStmt->execute([':salarm' => $formattedTime, ':enr' => $enr]);
                Logger::info('EmdSync: Status C (Alarm)', ['sender' => $sender, 'enr' => $enr, 'time' => $formattedTime]);
            } else {
                $allowedColumns = ['s1', 's2', 's3', 's4', 's7', 's8'];
                if (!in_array($statusColumn, $allowedColumns, true)) {
                    Logger::warning('EmdSync: Ungültige Status-Spalte', ['column' => $statusColumn, 'status_id' => $statusId]);
                    continue;
                }
                $sql        = "UPDATE intra_edivi SET $statusColumn = :status_time WHERE enr = :enr";
                $updateStmt = $this->pdo->prepare($sql);
                $updateStmt->execute([':status_time' => $formattedTime, ':enr' => $enr]);
                Logger::info('EmdSync: Status-Update', [
                    'status' => $statusValue,
                    'sender' => $sender,
                    'enr'    => $enr,
                    'column' => $statusColumn,
                ]);
            }

            $updatedThisStatus = false;
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                $updatedThisStatus = true;
            }

            // Fire Incident Status für Feuerwehr-Fahrzeuge (rd_type = 3)
            if ($rdType === 3 && $this->firetabActive()) {
                $findFireIncidentStmt = $this->pdo->prepare(
                    "SELECT id FROM intra_fire_incidents WHERE incident_number = :incident_number LIMIT 1"
                );
                $findFireIncidentStmt->execute([':incident_number' => (string) $missionNumber]);
                $fireIncidentRow = $findFireIncidentStmt->fetch(PDO::FETCH_ASSOC);

                if ($fireIncidentRow) {
                    $fireIncidentId = (int) $fireIncidentRow['id'];
                    $getVehicleIdStmt = $this->pdo->prepare(
                        "SELECT id FROM intra_fahrzeuge WHERE identifier = :identifier LIMIT 1"
                    );
                    $getVehicleIdStmt->execute([':identifier' => $vehicleIdentifier]);
                    $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

                    if ($vehicleIdRow) {
                        $vehicleId            = (int) $vehicleIdRow['id'];
                        $updateFireStatusStmt = $this->pdo->prepare("
                            UPDATE intra_fire_incident_vehicles
                            SET current_status = :status, status_updated_at = NOW()
                            WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                        ");
                        $updateFireStatusStmt->execute([
                            ':status'      => (string) $statusValue,
                            ':incident_id' => $fireIncidentId,
                            ':vehicle_id'  => $vehicleId,
                        ]);
                        if ($updateFireStatusStmt->rowCount() > 0) {
                            Logger::info('EmdSync: Fire Incident Status aktualisiert', [
                                'vehicle_id'  => $vehicleId,
                                'incident_id' => $fireIncidentId,
                                'status'      => $statusValue,
                            ]);
                        }
                    }
                }
            }

            if ($updatedThisStatus) {
                $successfulIds[] = $statusId;
            }
        }

        Logger::info('EmdSync: processStatusUpdatesInternal Ergebnis', [
            'updated'   => $updated,
            'not_found' => $notFound,
            'total'     => count($statuses),
        ]);

        return [
            'updated'        => $updated,
            'not_found'      => $notFound,
            'successful_ids' => $successfulIds,
            'total'          => count($statuses),
        ];
    }

    // ── V2: Unified Sync (Haupt-Einsatz-Verarbeitung) ────────────────

    /**
     * @param  array<string, mixed>  $receivedData
     */
    private function handleUnifiedSync(array $receivedData): Response
    {
        $isV2           = isset($receivedData['protocol_version']) && (int) $receivedData['protocol_version'] === 2;
        $v2StatusResult = ['successful_ids' => []];

        if ($isV2) {
            Logger::info('EmdSync: Protocol v2 erkannt - Unified Request');

            // 1. Status-Updates verarbeiten (vor Fahrzeug-Sync)
            $v2Statuses = $receivedData['status_updates']['statuses'] ?? [];
            if (!empty($v2Statuses)) {
                Logger::info('EmdSync V2: Verarbeite Status-Updates', ['count' => count($v2Statuses)]);
                $v2StatusResult = $this->processStatusUpdatesInternal($v2Statuses);
            }

            // 2. Fallback-Statuses verarbeiten (Fahrzeuge ohne aktiven Dispatch)
            $v2Fallbacks = $receivedData['fallback_statuses'] ?? [];
            if (!empty($v2Fallbacks)) {
                Logger::info('EmdSync V2: Verarbeite Fallback-Statuses', ['count' => count($v2Fallbacks)]);
                foreach ($v2Fallbacks as $fb) {
                    $fbVehicleName = $fb['vehicle_name'] ?? '';
                    $fbStatus      = $fb['status'] ?? '';
                    if (empty($fbVehicleName) || $fbStatus === '') {
                        continue;
                    }

                    $fbUpdatedAt = date('Y-m-d H:i:s');
                    if (!empty($fb['date']) && !empty($fb['time'])) {
                        $fbDt = DateTime::createFromFormat('d.m.Y H:i', $fb['date'] . ' ' . $fb['time'], new DateTimeZone('Europe/Berlin'));
                        if ($fbDt) {
                            $fbUpdatedAt = $fbDt->format('Y-m-d H:i:s');
                        }
                    }

                    $fbStmt = $this->pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE name = :name LIMIT 1");
                    $fbStmt->execute([':name' => $fbVehicleName]);
                    $fbVehicle = $fbStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$fbVehicle) {
                        Logger::warning('EmdSync V2 Fallback: Fahrzeug nicht gefunden', ['vehicle_name' => $fbVehicleName]);
                        continue;
                    }

                    $this->pdo->prepare(
                        "UPDATE intra_fahrzeuge SET current_status = :status, status_updated_at = :updated_at, status_source = 'no_dispatch' WHERE id = :id"
                    )->execute([
                        ':status'     => (string) $fbStatus,
                        ':updated_at' => $fbUpdatedAt,
                        ':id'         => (int) $fbVehicle['id'],
                    ]);
                    Logger::info('EmdSync V2 Fallback: Status-Update', [
                        'vehicle_name' => $fbVehicleName,
                        'status'       => $fbStatus,
                    ]);
                }
            }

            // 3. Dispatch-Logs: Nur Logging (keine Speicherung)
            if (isset($receivedData['dispatch_logs'])) {
                $dlMissions = $receivedData['dispatch_logs']['missions'] ?? [];
                Logger::info('EmdSync V2: Dispatch-Logs empfangen', ['count' => count($dlMissions)]);
            }

            // 4. Normalisiere dispatch_data -> data
            if (isset($receivedData['dispatch_data'])) {
                $receivedData['data'] = $receivedData['dispatch_data'];
            } elseif (!isset($receivedData['data'])) {
                $receivedData['data'] = ['vehicles' => []];
            }
        }

        // Fahrzeuge zur Verarbeitung einsammeln
        $vehicles               = $receivedData['data']['vehicles'] ?? [];
        $vehiclesByDispatch     = [];
        $dispatchDataByDispatch = [];

        if (!empty($vehicles)) {
            Logger::info('EmdSync: Fahrzeuge zur Verarbeitung', ['count' => count($vehicles)]);

            foreach ($vehicles as $vehicle) {
                $dispatchId = (int) ($vehicle['dispatch'] ?? 0);
                if ($dispatchId <= 0) {
                    continue;
                }

                if (!isset($vehiclesByDispatch[$dispatchId])) {
                    $vehiclesByDispatch[$dispatchId] = [];
                }
                $vehiclesByDispatch[$dispatchId][] = $vehicle;

                if (isset($vehicle['dispatch_data']) && !isset($dispatchDataByDispatch[$dispatchId])) {
                    $dispatchDataByDispatch[$dispatchId] = $vehicle['dispatch_data'];
                }
            }

            Logger::info('EmdSync: Eindeutige Einsatznummern', ['count' => count($vehiclesByDispatch)]);
        } else {
            Logger::info('EmdSync: Keine Fahrzeuge in der Anfrage');
        }

        // Zähler für die Response
        $processedDispatches     = 0;
        $createdEntries          = 0;
        $skippedDispatches       = 0;
        $createdFireIncidents    = 0;
        $newSitrepsFromDispatch  = 0;
        $fireIncidentsByDispatch = [];

        $this->pdo->beginTransaction();

        foreach ($vehiclesByDispatch as $dispatchId => $dispatchVehicles) {
            $result = $this->processDispatch(
                (int) $dispatchId,
                $dispatchVehicles,
                $dispatchDataByDispatch,
                $fireIncidentsByDispatch,
                $newSitrepsFromDispatch,
                $createdEntries,
                $createdFireIncidents,
            );
            if ($result === 'skipped') {
                $skippedDispatches++;
            } else {
                $processedDispatches++;
            }
        }

        // V2: Top-Level Lagemeldungen (falls separat von dispatch_data gesendet)
        if ($isV2 && isset($receivedData['lagemeldungen']) && is_array($receivedData['lagemeldungen'])) {
            $this->processTopLevelLagemeldungen(
                $receivedData['lagemeldungen'],
                $fireIncidentsByDispatch,
                $newSitrepsFromDispatch,
            );
        }

        // Lokale Lagemeldungen (noch nicht gesynct) für Response sammeln
        $situationReports = $this->collectLocalSitreps($fireIncidentsByDispatch);

        // Patientendaten die zum Senden markiert wurden (pat_synced = 2)
        $patientUpdates = $this->collectPendingPatientUpdates();

        // Status-Queue (ersetzt separaten emd-status-poll.php)
        $statusChanges = $this->collectPendingStatusQueue();

        $this->pdo->commit();

        Logger::info('EmdSync: Synchronisation abgeschlossen', [
            'processed_dispatches'     => $processedDispatches,
            'created_entries'          => $createdEntries,
            'skipped_dispatches'       => $skippedDispatches,
            'created_fire_incidents'   => $createdFireIncidents,
            'new_sitreps_from_dispatch' => $newSitrepsFromDispatch,
        ]);

        // Response zusammenbauen
        $response = [
            'success'    => true,
            'message'    => 'Synchronisation erfolgreich abgeschlossen',
            'statistics' => [
                'total_vehicles'            => count($vehicles),
                'unique_dispatches'         => count($vehiclesByDispatch),
                'processed_dispatches'      => $processedDispatches,
                'created_entries'           => $createdEntries,
                'skipped_dispatches'        => $skippedDispatches,
                'created_fire_incidents'    => $createdFireIncidents,
                'new_sitreps_from_dispatch' => $newSitrepsFromDispatch,
            ],
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

        if ($isV2) {
            $response['status_poll'] = ['status_changes' => $statusChanges];
            $response['status_ack']  = ['successful_ids' => $v2StatusResult['successful_ids']];
        }

        // Prüfe ob Fahrzeug-Import angefordert wurde (File-Flag)
        $flagFile = dirname(__DIR__, 4) . '/storage/emd_vehicle_import_request.flag';
        if (file_exists($flagFile)) {
            $response['request_vehicle_registry'] = true;
        }

        return Response::json($response);
    }

    // ── Per-Dispatch Verarbeitung ────────────────────────────────────

    /**
     * Verarbeitet einen einzelnen Einsatz aus der $vehiclesByDispatch-Map.
     *
     * WICHTIG: Die Fahrzeug-Trennung in $validVehicles (RD) und
     * $fireVehicles (FW) ist hier Bug-fix-kritisch — siehe Controller-
     * Header-Kommentar.
     *
     * @param  array<int, array<string, mixed>>  $dispatchVehicles
     * @param  array<int, array<string, mixed>>  $dispatchDataByDispatch
     * @param  array<int, int>                   $fireIncidentsByDispatch  (byref)
     */
    private function processDispatch(
        int $dispatchId,
        array $dispatchVehicles,
        array $dispatchDataByDispatch,
        array &$fireIncidentsByDispatch,
        int &$newSitrepsFromDispatch,
        int &$createdEntries,
        int &$createdFireIncidents,
    ): string {
        Logger::info('EmdSync: Verarbeite Einsatz', [
            'dispatch_id' => $dispatchId,
            'vehicle_count' => count($dispatchVehicles),
        ]);

        // ── Fahrzeuge in zwei Listen sortieren ──
        // $validVehicles: RD (rd_type 1+2) → erzeugen eNOTF-Protokolle
        // $fireVehicles:  FW (rd_type 3)   → erzeugen Fire-Incidents
        // Ein Fahrzeug gehört IMMER nur in eine Liste (FW-Bug-Fix).
        $validVehicles = [];
        $fireVehicles  = [];

        foreach ($dispatchVehicles as $vehicle) {
            $valueName = $vehicle['value'] ?? null;

            $stmt = $this->pdo->prepare("
                SELECT id, name, identifier, veh_type, rd_type
                FROM intra_fahrzeuge
                WHERE name = :name
                LIMIT 1
            ");
            $stmt->execute([':name' => $valueName]);
            $dbVehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbVehicle) {
                Logger::warning('EmdSync: Fahrzeug in DB nicht gefunden', ['name' => $valueName]);
                continue;
            }

            $rdType = (int) ($dbVehicle['rd_type'] ?? 0);
            if ($rdType === 0) {
                continue;
            }

            $vehicleRecord = [
                'name'         => $valueName,
                'identifier'   => $dbVehicle['identifier'],
                'rd_type'      => $rdType,
                'is_notarzt'   => ($rdType === 1),
                'is_transport' => ($rdType === 2),
                'is_fire'      => ($rdType === 3),
            ];

            if ($rdType === 3) {
                $fireVehicles[] = $vehicleRecord;
            } else {
                $validVehicles[] = $vehicleRecord;
            }
        }

        $hasFireVehicle = !empty($fireVehicles);

        if (empty($validVehicles) && !$hasFireVehicle) {
            Logger::warning('EmdSync: Keine gültigen Fahrzeuge für Einsatz', ['dispatch_id' => $dispatchId]);
            return 'skipped';
        }

        Logger::info('EmdSync: Einsatz-Zusammensetzung', [
            'dispatch_id' => $dispatchId,
            'rd_count'    => count($validVehicles),
            'fw_count'    => count($fireVehicles),
        ]);

        // ── Fire Incident erstellen/updaten ──
        if ($hasFireVehicle && $this->firetabActive()) {
            $this->upsertFireIncident(
                $dispatchId,
                $fireVehicles,
                $dispatchDataByDispatch,
                $fireIncidentsByDispatch,
                $createdFireIncidents,
            );
        }

        // ── Lagemeldungen aus dispatch_data verarbeiten ──
        $dispatchData = $dispatchDataByDispatch[$dispatchId] ?? null;
        if ($dispatchData && isset($dispatchData['lagemeldungen']) && is_array($dispatchData['lagemeldungen']) && isset($fireIncidentsByDispatch[$dispatchId])) {
            $this->processSitreps(
                (int) $fireIncidentsByDispatch[$dispatchId],
                $dispatchData['lagemeldungen'],
                $newSitrepsFromDispatch,
            );
        }

        // ── eNOTF-Protokolle für RD-Fahrzeuge erstellen ──
        $this->createEnotfEntries(
            $dispatchId,
            $validVehicles,
            $dispatchDataByDispatch,
            $createdEntries,
        );

        return 'processed';
    }

    /**
     * @param  array<int, array<string, mixed>>  $fireVehicles
     * @param  array<int, array<string, mixed>>  $dispatchDataByDispatch
     * @param  array<int, int>                   $fireIncidentsByDispatch  (byref)
     */
    private function upsertFireIncident(
        int $dispatchId,
        array $fireVehicles,
        array $dispatchDataByDispatch,
        array &$fireIncidentsByDispatch,
        int &$createdFireIncidents,
    ): void {
        Logger::info('EmdSync: Feuerwehreinsatz erkannt', [
            'dispatch_id' => $dispatchId,
            'fw_count'    => count($fireVehicles),
        ]);

        $checkFireIncidentStmt = $this->pdo->prepare(
            "SELECT id FROM intra_fire_incidents WHERE incident_number = :incident_number LIMIT 1"
        );
        $checkFireIncidentStmt->execute([':incident_number' => (string) $dispatchId]);
        $existingFireIncident = $checkFireIncidentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingFireIncident) {
            // Neuer Fire Incident
            $currentDateTime = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $dispatchData  = $dispatchDataByDispatch[$dispatchId] ?? null;
            $location      = 'BITTE ÄNDERN!';
            $keyword       = 'BITTE ÄNDERN!';
            $dispatchIssue = '';
            $callerName    = '';
            $callerContact = '';
            $locationX     = null;
            $locationY     = null;

            if ($dispatchData) {
                if (!empty($dispatchData['postal'])) {
                    $location = $dispatchData['postal'];
                }
                if (!empty($dispatchData['location_x'])) {
                    $locationX = (float) $dispatchData['location_x'];
                }
                if (!empty($dispatchData['location_y'])) {
                    $locationY = (float) $dispatchData['location_y'];
                }
                if (!empty($dispatchData['dispatch_code'])) {
                    $keyword = $dispatchData['dispatch_code'];
                }
                if (!empty($dispatchData['dispatch_issue'])) {
                    $dispatchIssue = $dispatchData['dispatch_issue'];
                }
                if (!empty($dispatchData['caller_name'])) {
                    $callerName = $dispatchData['caller_name'];
                }
                if (!empty($dispatchData['caller_phonenumber'])) {
                    $callerContact = $dispatchData['caller_phonenumber'];
                }
            }

            $notes = '';
            if (!empty($dispatchIssue)) {
                $notes = 'EINSATZMELDUNG: ' . $dispatchIssue . "\n\n";
            }
            $notes .= 'Automatisch erstellt durch Synchronisation';

            $insertFireIncidentStmt = $this->pdo->prepare("
                INSERT INTO intra_fire_incidents
                (incident_number, location, keyword, caller_name, caller_contact, started_at, status, notes, created_by, created_at, location_x, location_y)
                VALUES (:incident_number, :location, :keyword, :caller_name, :caller_contact, :started_at, 0, :notes, NULL, :created_at, :location_x, :location_y)
            ");
            $insertFireIncidentStmt->execute([
                ':incident_number' => (string) $dispatchId,
                ':location'        => $location,
                ':keyword'         => $keyword,
                ':caller_name'     => $callerName ?: null,
                ':caller_contact'  => $callerContact ?: null,
                ':started_at'      => $currentDateTime,
                ':notes'           => $notes,
                ':created_at'      => $currentDateTime,
                ':location_x'      => $locationX,
                ':location_y'      => $locationY,
            ]);

            $fireIncidentId = (int) $this->pdo->lastInsertId();
            Logger::info('EmdSync: Fire Incident erstellt', [
                'fire_incident_id' => $fireIncidentId,
                'dispatch_id'      => $dispatchId,
                'location'         => $location,
                'keyword'          => $keyword,
            ]);

            // Alle FW-Fahrzeuge hinzufügen
            $this->attachFireVehicles($fireIncidentId, $fireVehicles, true);

            // Log-Eintrag
            $insertLogStmt = $this->pdo->prepare("
                INSERT INTO intra_fire_incident_log
                (incident_id, action_type, action_description, vehicle_id, operator_id, created_by, created_at)
                VALUES (:incident_id, 'created', :action_description, NULL, NULL, NULL, NOW())
            ");
            $insertLogStmt->execute([
                ':incident_id'        => $fireIncidentId,
                ':action_description' => 'Einsatz automatisch durch Sync erstellt',
            ]);

            $createdFireIncidents++;
            $fireIncidentsByDispatch[$dispatchId] = $fireIncidentId;
        } else {
            $fireIncidentId                         = (int) $existingFireIncident['id'];
            $fireIncidentsByDispatch[$dispatchId]   = $fireIncidentId;
            Logger::info('EmdSync: Fire Incident existiert bereits', [
                'fire_incident_id' => $fireIncidentId,
                'dispatch_id'      => $dispatchId,
            ]);

            // Neue Fahrzeuge hinzufügen falls noch nicht vorhanden
            $this->attachFireVehicles($fireIncidentId, $fireVehicles, false);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fireVehicles
     */
    private function attachFireVehicles(int $fireIncidentId, array $fireVehicles, bool $initialCreate): void
    {
        foreach ($fireVehicles as $fireVehicle) {
            $getVehicleIdStmt = $this->pdo->prepare(
                "SELECT id FROM intra_fahrzeuge WHERE identifier = :identifier LIMIT 1"
            );
            $getVehicleIdStmt->execute([':identifier' => $fireVehicle['identifier']]);
            $vehicleIdRow = $getVehicleIdStmt->fetch(PDO::FETCH_ASSOC);

            if (!$vehicleIdRow) {
                continue;
            }

            $vehicleId = (int) $vehicleIdRow['id'];

            $checkVehicleAssignmentStmt = $this->pdo->prepare("
                SELECT id FROM intra_fire_incident_vehicles
                WHERE incident_id = :incident_id AND vehicle_id = :vehicle_id
                LIMIT 1
            ");
            $checkVehicleAssignmentStmt->execute([
                ':incident_id' => $fireIncidentId,
                ':vehicle_id'  => $vehicleId,
            ]);

            if ($checkVehicleAssignmentStmt->fetch()) {
                continue;
            }

            $insertVehicleStmt = $this->pdo->prepare("
                INSERT INTO intra_fire_incident_vehicles
                (incident_id, vehicle_id, from_other_org, created_by, created_at)
                VALUES (:incident_id, :vehicle_id, 0, NULL, NOW())
            ");
            $insertVehicleStmt->execute([
                ':incident_id' => $fireIncidentId,
                ':vehicle_id'  => $vehicleId,
            ]);

            // Bei bestehendem Incident zusätzlich Log-Eintrag
            if (!$initialCreate) {
                $insertLogStmt = $this->pdo->prepare("
                    INSERT INTO intra_fire_incident_log
                    (incident_id, action_type, action_description, vehicle_id, operator_id, created_by, created_at)
                    VALUES (:incident_id, 'vehicle_added', :action_description, :vehicle_id, NULL, NULL, NOW())
                ");
                $insertLogStmt->execute([
                    ':incident_id'        => $fireIncidentId,
                    ':vehicle_id'         => $vehicleId,
                    ':action_description' => "Fahrzeug {$fireVehicle['name']} durch Sync hinzugefügt",
                ]);
            }

            Logger::info('EmdSync: FW-Fahrzeug zugewiesen', [
                'vehicle_id'       => $vehicleId,
                'fire_incident_id' => $fireIncidentId,
                'initial_create'   => $initialCreate,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lagemeldungen
     */
    private function processSitreps(int $fireIncidentId, array $lagemeldungen, int &$newSitrepsFromDispatch): void
    {
        $sitrepsToProcess = array_filter($lagemeldungen, function ($entry) {
            return isset($entry['type']) && in_array($entry['type'], [
                'control_dispatch_form_entry_type_situation_report',
                'control_dispatch_form_entry_type_situation_report_important',
            ], true);
        });

        if (empty($sitrepsToProcess)) {
            return;
        }

        Logger::info('EmdSync: Verarbeite Lagemeldungen', [
            'fire_incident_id' => $fireIncidentId,
            'count'            => count($sitrepsToProcess),
        ]);

        foreach ($sitrepsToProcess as $sitrep) {
            $sitrepText = trim($sitrep['text'] ?? '');
            $sitrepDate = $sitrep['date'] ?? '';
            $sitrepTime = $sitrep['time'] ?? '';

            if (empty($sitrepText) || empty($sitrepDate) || empty($sitrepTime)) {
                continue;
            }

            $reportTime = DateTime::createFromFormat('d.m.Y H:i', $sitrepDate . ' ' . $sitrepTime, new DateTimeZone('Europe/Berlin'));
            if (!$reportTime) {
                continue;
            }
            $reportTime->setTimezone(new DateTimeZone('UTC'));
            $reportTimeFormatted = $reportTime->format('Y-m-d H:i:s');

            // Deduplizierung: gleicher Text im ±15-Min-Fenster
            $checkDuplicateStmt = $this->pdo->prepare("
                SELECT id FROM intra_fire_incident_sitreps
                WHERE incident_id = :incident_id
                AND text = :text
                AND ABS(TIMESTAMPDIFF(SECOND, report_time, :report_time)) <= 900
                LIMIT 1
            ");
            $checkDuplicateStmt->execute([
                ':incident_id' => $fireIncidentId,
                ':text'        => $sitrepText,
                ':report_time' => $reportTimeFormatted,
            ]);

            if ($checkDuplicateStmt->fetch()) {
                continue;
            }

            $insertSitrepStmt = $this->pdo->prepare("
                INSERT INTO intra_fire_incident_sitreps
                (incident_id, report_time, text, vehicle_radio_name, vehicle_id, created_by, source, synced)
                VALUES (:incident_id, :report_time, :text, 'Leitstelle', NULL, NULL, 'leitstelle', 1)
            ");
            $insertSitrepStmt->execute([
                ':incident_id' => $fireIncidentId,
                ':report_time' => $reportTimeFormatted,
                ':text'        => $sitrepText,
            ]);

            $newSitrepsFromDispatch++;
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $lagemeldungenByDispatch
     * @param  array<int, int>                               $fireIncidentsByDispatch
     */
    private function processTopLevelLagemeldungen(
        array $lagemeldungenByDispatch,
        array $fireIncidentsByDispatch,
        int &$newSitrepsFromDispatch,
    ): void {
        foreach ($lagemeldungenByDispatch as $lageDId => $lageEntries) {
            if (!isset($fireIncidentsByDispatch[$lageDId]) || !is_array($lageEntries)) {
                continue;
            }
            $this->processSitreps((int) $fireIncidentsByDispatch[$lageDId], $lageEntries, $newSitrepsFromDispatch);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $validVehicles
     * @param  array<int, array<string, mixed>>  $dispatchDataByDispatch
     */
    private function createEnotfEntries(
        int $dispatchId,
        array $validVehicles,
        array $dispatchDataByDispatch,
        int &$createdEntries,
    ): void {
        if (!$this->enotfActive()) {
            return;
        }

        $checkExistingStmt = $this->pdo->prepare("
            SELECT enr
            FROM intra_edivi
            WHERE enr = :enr OR enr LIKE :enr_pattern
        ");
        $checkExistingStmt->execute([
            ':enr'         => $dispatchId,
            ':enr_pattern' => $dispatchId . '_%',
        ]);
        $existingEnrs = $checkExistingStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($validVehicles as $vehicle) {
            $vehicleIdentifier = $vehicle['identifier'];
            $fieldToCheck      = $vehicle['is_notarzt'] ? 'fzg_na' : 'fzg_transp';

            $checkVehicleStmt = $this->pdo->prepare("
                SELECT enr
                FROM intra_edivi
                WHERE (enr = :enr OR enr LIKE :enr_pattern)
                AND $fieldToCheck = :vehicle_id
                LIMIT 1
            ");
            $checkVehicleStmt->execute([
                ':enr'         => $dispatchId,
                ':enr_pattern' => $dispatchId . '_%',
                ':vehicle_id'  => $vehicleIdentifier,
            ]);
            $existingEntry = $checkVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingEntry) {
                continue;
            }

            // Neue ENR bestimmen: basis oder basis_N.
            // WICHTIG: ENRs werden durchgängig als string gehandhabt, weil
            // der Suffix-Fall ("123_1") string ist und uns sonst strict
            // in_array-Vergleiche (string vs. int) reinreiten würden.
            $enrToUse = null;
            $baseEnr  = (string) $dispatchId;
            if (!in_array($baseEnr, $existingEnrs, true)) {
                $enrToUse = $baseEnr;
            } else {
                $suffix = 1;
                while (true) {
                    $testEnr = $baseEnr . '_' . $suffix;
                    if (!in_array($testEnr, $existingEnrs, true)) {
                        $enrToUse = $testEnr;
                        break;
                    }
                    $suffix++;
                }
            }

            // Patientendaten aus dispatch_data extrahieren
            $dispatchData     = $dispatchDataByDispatch[$dispatchId] ?? null;
            $patientData      = $this->extractPatientData($dispatchData);
            $sonderrechteAnfahrt = $this->extractSonderrechte($dispatchData);

            $currentDate = date('Y-m-d');
            $currentTime = date('H:i');

            if ($vehicle['is_notarzt']) {
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_na, edatum, ezeit, prot_by, patname, pat_vorname, pat_nachname, patgebdat, sonderrechte_anfahrt, created_at, createdby)
                    VALUES (:enr, :fzg_na, :edatum, :ezeit, :prot_by, :patname, :pat_vorname, :pat_nachname, :patgebdat, :sonderrechte_anfahrt, NOW(), 1)
                ");
                $insertStmt->execute([
                    ':enr'                  => $enrToUse,
                    ':fzg_na'               => $vehicle['identifier'],
                    ':edatum'               => $currentDate,
                    ':ezeit'                => $currentTime,
                    ':prot_by'              => 1,
                    ':patname'              => $patientData['name'],
                    ':pat_vorname'          => $patientData['vorname'],
                    ':pat_nachname'         => $patientData['nachname'],
                    ':patgebdat'            => $patientData['birthdate'],
                    ':sonderrechte_anfahrt' => $sonderrechteAnfahrt,
                ]);

                Logger::info('EmdSync: Notarzt-Eintrag erstellt', [
                    'enr'        => $enrToUse,
                    'identifier' => $vehicle['identifier'],
                ]);
            } else {
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_transp, edatum, ezeit, prot_by, patname, pat_vorname, pat_nachname, patgebdat, sonderrechte_anfahrt, created_at, createdby)
                    VALUES (:enr, :fzg_transp, :edatum, :ezeit, :prot_by, :patname, :pat_vorname, :pat_nachname, :patgebdat, :sonderrechte_anfahrt, NOW(), 1)
                ");
                $insertStmt->execute([
                    ':enr'                  => $enrToUse,
                    ':fzg_transp'           => $vehicle['identifier'],
                    ':edatum'               => $currentDate,
                    ':ezeit'                => $currentTime,
                    ':prot_by'              => 0,
                    ':patname'              => $patientData['name'],
                    ':pat_vorname'          => $patientData['vorname'],
                    ':pat_nachname'         => $patientData['nachname'],
                    ':patgebdat'            => $patientData['birthdate'],
                    ':sonderrechte_anfahrt' => $sonderrechteAnfahrt,
                ]);

                Logger::info('EmdSync: Transport-Eintrag erstellt', [
                    'enr'        => $enrToUse,
                    'identifier' => $vehicle['identifier'],
                ]);
            }

            $existingEnrs[] = $enrToUse;
            $createdEntries++;
        }
    }

    /**
     * @param  array<string, mixed>|null  $dispatchData
     * @return array{name: ?string, vorname: ?string, nachname: ?string, birthdate: ?string}
     */
    private function extractPatientData(?array $dispatchData): array
    {
        $result = ['name' => null, 'vorname' => null, 'nachname' => null, 'birthdate' => null];

        if (!$dispatchData || !isset($dispatchData['patienten']) || !is_array($dispatchData['patienten']) || empty($dispatchData['patienten'])) {
            return $result;
        }

        $patient = $dispatchData['patienten'][0];

        if (isset($patient['nachname']) || isset($patient['vorname'])) {
            $result['nachname'] = !empty($patient['nachname']) ? $patient['nachname'] : null;
            $result['vorname']  = !empty($patient['vorname']) ? $patient['vorname'] : null;

            if ($result['nachname'] && $result['vorname']) {
                $result['name'] = $result['nachname'] . ', ' . $result['vorname'];
            } elseif ($result['nachname']) {
                $result['name'] = $result['nachname'];
            } elseif ($result['vorname']) {
                $result['name'] = $result['vorname'];
            }
        }

        // Geburtsdatum aus Alter ableiten (01.01.XXXX)
        if (isset($patient['alter']) && is_numeric($patient['alter'])) {
            $alter              = (int) $patient['alter'];
            $currentYear        = (int) date('Y');
            $result['birthdate'] = ($currentYear - $alter) . '-01-01';
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $dispatchData
     */
    private function extractSonderrechte(?array $dispatchData): ?string
    {
        if ($dispatchData && isset($dispatchData['bluelight'])) {
            return ($dispatchData['bluelight'] === 'yes') ? 'ja' : 'nein';
        }
        return null;
    }

    // ── Response-Collection ──────────────────────────────────────────

    /**
     * @param  array<int, int>  $fireIncidentsByDispatch
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function collectLocalSitreps(array $fireIncidentsByDispatch): array
    {
        $situationReports       = [];
        $sitrepIdsToMarkSynced = [];

        foreach ($fireIncidentsByDispatch as $dId => $fIncidentId) {
            $localSitrepsStmt = $this->pdo->prepare("
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

            if (empty($localSitreps)) {
                continue;
            }

            $situationReports[(string) $dId] = [];
            foreach ($localSitreps as $ls) {
                $reportDt = DateTime::createFromFormat('Y-m-d H:i:s', $ls['report_time'], new DateTimeZone('UTC'));
                if ($reportDt) {
                    $reportDt->setTimezone(new DateTimeZone('Europe/Berlin'));
                }
                $situationReports[(string) $dId][] = [
                    'text'   => $ls['text'],
                    'time'   => $reportDt ? $reportDt->format('H:i') : '',
                    'date'   => $reportDt ? $reportDt->format('d.m.Y') : '',
                    'sender' => $ls['vehicle_radio_name'] ?? $ls['sys_name'] ?? 'Unbekannt',
                ];
                $sitrepIdsToMarkSynced[] = (int) $ls['id'];
            }
        }

        if (!empty($sitrepIdsToMarkSynced)) {
            $placeholders = implode(',', array_fill(0, count($sitrepIdsToMarkSynced), '?'));
            $this->pdo->prepare("UPDATE intra_fire_incident_sitreps SET synced = 1 WHERE id IN ($placeholders)")
                ->execute($sitrepIdsToMarkSynced);
        }

        return $situationReports;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectPendingPatientUpdates(): array
    {
        if (!$this->enotfActive()) {
            return [];
        }

        $patSyncStmt = $this->pdo->prepare("
            SELECT enr, pat_vorname, pat_nachname, patgebdat, prot_by, fzg_na, fzg_transp, ziel_poi
            FROM intra_edivi
            WHERE pat_synced = 2
        ");
        $patSyncStmt->execute();
        $pendingPatients = $patSyncStmt->fetchAll(PDO::FETCH_ASSOC);

        $patientUpdates           = [];
        $patientEnrsToMarkSynced = [];

        foreach ($pendingPatients as $pp) {
            $patAge = null;
            if (!empty($pp['patgebdat'])) {
                $birthDate = new DateTime($pp['patgebdat']);
                $now       = new DateTime();
                $patAge    = $now->diff($birthDate)->y;
            }

            // Funkrufname: fzg_transp bevorzugt, fzg_na als Fallback
            $funkrufname       = null;
            $vehicleIdentifier = !empty($pp['fzg_transp']) ? $pp['fzg_transp'] : ($pp['fzg_na'] ?? null);
            if (!empty($vehicleIdentifier)) {
                $vehNameStmt = $this->pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :identifier LIMIT 1");
                $vehNameStmt->execute([':identifier' => $vehicleIdentifier]);
                $funkrufname = $vehNameStmt->fetchColumn() ?: null;
            }

            $patientUpdates[(string) $pp['enr']] = [
                'vorname'       => !empty($pp['pat_vorname']) ? $pp['pat_vorname'] : 'Unbekannt',
                'nachname'      => !empty($pp['pat_nachname']) ? $pp['pat_nachname'] : 'Unbekannt',
                'alter'         => $patAge,
                'funkrufname'   => $funkrufname,
                'transportziel' => !empty($pp['ziel_poi']) ? $pp['ziel_poi'] : null,
            ];
            $patientEnrsToMarkSynced[] = $pp['enr'];
        }

        if (!empty($patientEnrsToMarkSynced)) {
            $placeholders = implode(',', array_fill(0, count($patientEnrsToMarkSynced), '?'));
            $this->pdo->prepare("UPDATE intra_edivi SET pat_synced = 1 WHERE enr IN ($placeholders)")
                ->execute($patientEnrsToMarkSynced);
        }

        return $patientUpdates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectPendingStatusQueue(): array
    {
        if (!$this->firetabActive()) {
            return [];
        }

        $statusQueueStmt = $this->pdo->prepare("
            SELECT id, vehicle_name, new_status, incident_number, created_at
            FROM intra_fire_status_queue
            WHERE delivered = 0
            ORDER BY created_at ASC
        ");
        $statusQueueStmt->execute();
        $pendingStatuses = $statusQueueStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingStatuses)) {
            return [];
        }

        $statusChanges      = [];
        $statusIdsToDeliver = [];

        foreach ($pendingStatuses as $sq) {
            $statusChanges[] = [
                'vehicle_name'    => $sq['vehicle_name'],
                'status'          => $sq['new_status'],
                'incident_number' => $sq['incident_number'],
                'timestamp'       => (new DateTime($sq['created_at']))->format('d.m.Y H:i'),
            ];
            $statusIdsToDeliver[] = (int) $sq['id'];
        }

        $placeholders = implode(',', array_fill(0, count($statusIdsToDeliver), '?'));
        $this->pdo->prepare("UPDATE intra_fire_status_queue SET delivered = 1 WHERE id IN ($placeholders)")
            ->execute($statusIdsToDeliver);

        return $statusChanges;
    }

    /**
     * Fire-Incidents, Status-Queue und FW-Fahrzeugstatus leben im
     * fireTab-Plugin — ohne installiertes Plugin existieren die Tabellen
     * nicht, also werden alle FW-Codepfade übersprungen.
     */
    private function firetabActive(): bool
    {
        return app(\App\Plugins\PluginLoader::class)->isActive('firetab');
    }

    /**
     * eNOTF-Protokolle (intra_edivi) leben im eNOTF-Plugin — ohne
     * installiertes Plugin werden Protokoll-Erstellung, Status- und
     * Patienten-Sync übersprungen.
     */
    private function enotfActive(): bool
    {
        return app(\App\Plugins\PluginLoader::class)->isActive('enotf');
    }
}
