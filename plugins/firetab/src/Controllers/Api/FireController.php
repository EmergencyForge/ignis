<?php

declare(strict_types=1);

namespace Plugin\Firetab\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use PDO;
use PDOException;

/**
 * Fire-Incident-API: Fahrzeug-Status-Updates (in-Einsatz-Polling von der
 * Tactical-Map-UI) und Bulk-Delete-Empty (Admin-Tool zum Aufräumen leerer
 * Fire-Incident-Protokolle).
 */
final class FireController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/fire/status
     *
     * Vehicle-Session-auth: erwartet `$_SESSION['einsatz_vehicle_id']` und
     * `$_SESSION['einsatz_operator_id']` — wird vom Fahrzeug nach Login
     * im Einsatz-Modul gesetzt.
     *
     * Body: { "action": "get_status" }  oder
     *       { "action": "set_status", "incident_id": N, "new_status": "0"|..|"6" }
     */
    public function status(Request $request): Response
    {
        if (!isset($_SESSION['einsatz_vehicle_id'], $_SESSION['einsatz_operator_id'])) {
            return Response::json(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $data = $request->json();
        if (!is_array($data) || !isset($data['action'])) {
            return Response::json(['success' => false, 'error' => 'Ungültige Anfrage'], 400);
        }

        $vehicleId = (int) $_SESSION['einsatz_vehicle_id'];

        return match ($data['action']) {
            'get_status' => $this->getVehicleStatus($vehicleId),
            'set_status' => $this->setVehicleStatus($data, $vehicleId),
            default      => Response::json(['success' => false, 'error' => 'Unbekannte Aktion'], 400),
        };
    }

    private function getVehicleStatus(int $vehicleId): Response
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT current_status, status_source FROM intra_fahrzeuge WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $vehicleId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return Response::json([
                'success'        => true,
                'current_status' => $row['current_status'] ?? null,
                'status_source'  => $row['status_source']  ?? null,
            ]);
        } catch (PDOException $e) {
            Logger::error('Fire: get_status Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setVehicleStatus(array $data, int $vehicleId): Response
    {
        $incidentId = isset($data['incident_id']) ? (int) $data['incident_id'] : 0;
        $newStatus  = (string) ($data['new_status'] ?? '');

        $allowedStatuses = ['0', '1', '2', '3', '4', '5', '6'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return Response::json(['success' => false, 'error' => 'Ungültiger Status'], 400);
        }
        if ($incidentId <= 0) {
            return Response::json(['success' => false, 'error' => 'Ungültige Einsatz-ID'], 400);
        }

        try {
            $checkStmt = $this->pdo->prepare("
                SELECT fiv.id, fi.incident_number
                FROM intra_fire_incident_vehicles fiv
                JOIN intra_fire_incidents fi ON fiv.incident_id = fi.id
                WHERE fiv.vehicle_id = :vehicle_id AND fiv.incident_id = :incident_id
                LIMIT 1
            ");
            $checkStmt->execute([':vehicle_id' => $vehicleId, ':incident_id' => $incidentId]);
            $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Fahrzeug nicht diesem Einsatz zugeordnet',
                ], 403);
            }

            $incidentNumber = $assignment['incident_number'];

            $vehStmt = $this->pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE id = ? LIMIT 1");
            $vehStmt->execute([$vehicleId]);
            $vehicleName = $vehStmt->fetchColumn() ?: 'Unbekannt';

            $this->pdo->beginTransaction();

            // 1. Status auf intra_fire_incident_vehicles aktualisieren
            $this->pdo->prepare("
                UPDATE intra_fire_incident_vehicles
                SET current_status = :status, status_updated_at = NOW()
                WHERE vehicle_id = :vehicle_id AND incident_id = :incident_id
            ")->execute([
                ':status'      => $newStatus,
                ':vehicle_id'  => $vehicleId,
                ':incident_id' => $incidentId,
            ]);

            // 2. Status-Queue für FiveM-Polling
            $this->pdo->prepare("
                INSERT INTO intra_fire_status_queue
                (vehicle_id, vehicle_name, incident_number, new_status)
                VALUES (:vehicle_id, :vehicle_name, :incident_number, :new_status)
            ")->execute([
                ':vehicle_id'      => $vehicleId,
                ':vehicle_name'    => $vehicleName,
                ':incident_number' => $incidentNumber,
                ':new_status'      => $newStatus,
            ]);

            // 3. Audit-Log
            $statusLabels = [
                '0' => 'Dringender Sprechwunsch',
                '1' => 'Einsatzbereit Funk',
                '2' => 'Einsatzbereit Wache',
                '3' => 'Einsatz übernommen',
                '4' => 'Am Einsatzort',
                '5' => 'Sprechwunsch',
                '6' => 'Nicht einsatzbereit',
            ];
            $this->pdo->prepare("
                INSERT INTO intra_fire_incident_log
                (incident_id, action_type, action_description, vehicle_id, operator_id, created_by)
                VALUES (?, 'status_changed', ?, ?, ?, ?)
            ")->execute([
                $incidentId,
                "Status auf $newStatus (" . $statusLabels[$newStatus] . ") geändert",
                $vehicleId,
                $_SESSION['einsatz_operator_id'] ?? null,
                $_SESSION['userid'] ?? null,
            ]);

            // 4. intra_fahrzeuge auch updaten (für die Status-Anzeige)
            $this->pdo->prepare("
                UPDATE intra_fahrzeuge
                SET current_status = :status, status_updated_at = NOW(), status_source = 'incident'
                WHERE id = :id
            ")->execute([':status' => $newStatus, ':id' => $vehicleId]);

            $this->pdo->commit();

            return Response::json(['success' => true, 'new_status' => $newStatus]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('Fire: set_status Fehler', [
                'error'       => $e->getMessage(),
                'vehicle_id'  => $vehicleId,
                'incident_id' => $incidentId,
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Datenbankfehler: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/fire/bulk-delete-empty — liefert die verfügbaren Felder
     * POST /api/fire/bulk-delete-empty — Preview oder echter Bulk-Delete
     *
     * POST-Body (form-data):
     *   - fields[]:   string[] — zu prüfende Felder (incident_number, location, keyword, leader_id, notes, no_vehicles)
     *   - preview:    wenn gesetzt → Preview, kein Delete
     *   - timePeriod: "all" | "7" | "30" | ...
     *   - statusFilter: "all" | "unfinalized" | "finalized"
     */
    public function bulkDeleteEmpty(Request $request): Response
    {
        $availableFields = [
            'incident_number' => 'Einsatznummer',
            'location'        => 'Einsatzort',
            'keyword'         => 'Stichwort',
            'leader_id'       => 'Einsatzleiter',
            'notes'           => 'Einsatzgeschehen',
            'no_vehicles'     => 'Keine Fahrzeuge zugewiesen',
        ];

        if (strtoupper($request->method) === 'GET') {
            return Response::json(['success' => true, 'fields' => $availableFields]);
        }

        try {
            $selectedFields = $request->post['fields'] ?? ['location'];
            $isPreview      = isset($request->post['preview']);
            $timePeriod     = (string) ($request->post['timePeriod']   ?? '30');
            $statusFilter   = (string) ($request->post['statusFilter'] ?? 'all');

            $fieldsToCheck = array_intersect($selectedFields, array_keys($availableFields));
            if (empty($fieldsToCheck)) {
                return Response::json(['success' => false, 'message' => 'Keine gültigen Felder ausgewählt']);
            }

            $conditions = [];
            foreach ($fieldsToCheck as $field) {
                $conditions[] = match ($field) {
                    'leader_id'   => '(i.leader_id IS NULL)',
                    'no_vehicles' => '(SELECT COUNT(*) FROM intra_fire_incident_vehicles v WHERE v.incident_id = i.id) = 0',
                    'notes'       => "(i.notes IS NULL OR i.notes = '')",
                    default       => "(i.{$field} IS NULL OR i.{$field} = '')",
                };
            }
            $whereClause = implode(' AND ', $conditions);

            $selectedFieldsLabel = implode(', ', array_map(
                fn ($f) => $availableFields[$f] ?? $f,
                $fieldsToCheck
            ));

            $timeCondition = '';
            if ($timePeriod !== 'all') {
                $days          = (int) $timePeriod;
                $timeCondition = "AND i.created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)";
            }

            $statusCondition = match ($statusFilter) {
                'unfinalized' => 'AND i.finalized = 0',
                'finalized'   => 'AND i.finalized = 1',
                default       => '',
            };

            if ($isPreview) {
                $query = "
                    SELECT i.id, i.incident_number, i.location, i.keyword, i.created_at, i.finalized,
                        m.fullname AS leader_name
                    FROM intra_fire_incidents i
                    LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
                    WHERE i.archived = 0
                    AND ({$whereClause})
                    {$timeCondition}
                    {$statusCondition}
                    ORDER BY i.created_at DESC
                ";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute();
                $protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return Response::json([
                    'success'             => true,
                    'protocols'           => $protocols,
                    'count'               => count($protocols),
                    'selectedFieldsLabel' => $selectedFieldsLabel,
                ]);
            }

            // Count before delete
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM intra_fire_incidents i
                WHERE i.archived = 0
                AND ({$whereClause})
                {$timeCondition}
                {$statusCondition}
            ");
            $countStmt->execute();
            $count = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            if ($count === 0) {
                return Response::json([
                    'success' => true,
                    'message' => 'Keine passenden Protokolle gefunden',
                    'deleted' => 0,
                ]);
            }

            // Soft-delete via archived=1
            $userId = (int) ($_SESSION['userid'] ?? 0);
            $deleteStmt = $this->pdo->prepare("
                UPDATE intra_fire_incidents i
                SET i.archived = 1,
                    i.archived_at = NOW(),
                    i.archived_by = :userId,
                    i.status = 4,
                    i.updated_by = :userId2,
                    i.updated_at = NOW()
                WHERE i.archived = 0
                AND ({$whereClause})
                {$timeCondition}
                {$statusCondition}
            ");
            $deleteStmt->execute(['userId' => $userId, 'userId2' => $userId]);
            $affectedRows = $deleteStmt->rowCount();

            $timeLabel   = $timePeriod === 'all' ? 'alle' : "letzte {$timePeriod} Tage";
            $statusLabel = match ($statusFilter) {
                'unfinalized' => ', nur unfertige',
                'finalized'   => ', nur abgeschlossene',
                default       => '',
            };

            (new AuditLogger($this->pdo))->log(
                $userId,
                "Bulk-Delete: {$affectedRows} Einsatzprotokolle gelöscht",
                "Gelöschte Protokolle mit leeren Feldern ({$selectedFieldsLabel}), Zeitraum: {$timeLabel}{$statusLabel}",
                'Feuerwehr',
                0
            );

            if (class_exists(\App\Helpers\Flash::class)) {
                \App\Helpers\Flash::set('success', "Es wurden {$affectedRows} Einsatzprotokolle erfolgreich gelöscht.");
            }

            return Response::json([
                'success' => true,
                'message' => "{$affectedRows} Protokolle wurden gelöscht",
                'deleted' => $affectedRows,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Fire: bulk-delete-empty Fehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'message' => 'Fehler beim Löschen: ' . $e->getMessage(),
            ], 500);
        }
    }
}
