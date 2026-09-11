<?php

declare(strict_types=1);

namespace Plugin\Firetab\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\Firetab\Models\FireIncidentLogEntry;
use Plugin\Firetab\Models\FireIncidentVehicle;
use Plugin\Firetab\Models\FireStatusQueueEntry;

/**
 * Fire-Incident-API: Fahrzeug-Status-Updates (in-Einsatz-Polling von der
 * Tactical-Map-UI) und Bulk-Delete-Empty (Admin-Tool zum Aufräumen leerer
 * Fire-Incident-Protokolle).
 */
final class FireController
{
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
            $row = Capsule::table('intra_fahrzeuge')
                ->where('id', $vehicleId)
                ->select(['current_status', 'status_source'])
                ->first();

            return Response::json([
                'success'        => true,
                'current_status' => $row->current_status ?? null,
                'status_source'  => $row->status_source  ?? null,
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
            $assignment = Capsule::table('intra_fire_incident_vehicles as fiv')
                ->join('intra_fire_incidents as fi', 'fiv.incident_id', '=', 'fi.id')
                ->where('fiv.vehicle_id', $vehicleId)
                ->where('fiv.incident_id', $incidentId)
                ->select(['fiv.id', 'fi.incident_number'])
                ->first();

            if (!$assignment) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Fahrzeug nicht diesem Einsatz zugeordnet',
                ], 403);
            }

            $incidentNumber = $assignment->incident_number;

            $vehicleName = Capsule::table('intra_fahrzeuge')
                ->where('id', $vehicleId)
                ->value('name') ?: 'Unbekannt';

            Capsule::connection()->transaction(function () use ($vehicleId, $incidentId, $newStatus, $vehicleName, $incidentNumber): void {
                // 1. Status auf intra_fire_incident_vehicles aktualisieren
                FireIncidentVehicle::where('vehicle_id', $vehicleId)
                    ->where('incident_id', $incidentId)
                    ->update([
                        'current_status'    => $newStatus,
                        'status_updated_at' => Capsule::raw('NOW()'),
                    ]);

                // 2. Status-Queue für FiveM-Polling
                FireStatusQueueEntry::create([
                    'vehicle_id'      => $vehicleId,
                    'vehicle_name'    => $vehicleName,
                    'incident_number' => $incidentNumber,
                    'new_status'      => $newStatus,
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
                FireIncidentLogEntry::create([
                    'incident_id'        => $incidentId,
                    'action_type'        => 'status_changed',
                    'action_description' => "Status auf $newStatus (" . $statusLabels[$newStatus] . ") geändert",
                    'vehicle_id'         => $vehicleId,
                    'operator_id'        => $_SESSION['einsatz_operator_id'] ?? null,
                    'created_by'         => $_SESSION['userid'] ?? null,
                ]);

                // 4. intra_fahrzeuge auch updaten (für die Status-Anzeige)
                Capsule::table('intra_fahrzeuge')
                    ->where('id', $vehicleId)
                    ->update([
                        'current_status'    => $newStatus,
                        'status_updated_at' => Capsule::raw('NOW()'),
                        'status_source'     => 'incident',
                    ]);
            });

            return Response::json(['success' => true, 'new_status' => $newStatus]);
        } catch (PDOException $e) {
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

            // Bedingungen stammen ausschließlich aus der Whitelist oben —
            // keine Nutzereingaben im SQL.
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

            $baseQuery = function () use ($whereClause, $timePeriod, $statusFilter) {
                $query = Capsule::table('intra_fire_incidents as i')
                    ->where('i.archived', 0)
                    ->whereRaw("({$whereClause})");

                if ($timePeriod !== 'all') {
                    $days = (int) $timePeriod;
                    $query->whereRaw("i.created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)");
                }

                match ($statusFilter) {
                    'unfinalized' => $query->where('i.finalized', 0),
                    'finalized'   => $query->where('i.finalized', 1),
                    default       => null,
                };

                return $query;
            };

            if ($isPreview) {
                $protocols = $baseQuery()
                    ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
                    ->select([
                        'i.id', 'i.incident_number', 'i.location', 'i.keyword',
                        'i.created_at', 'i.finalized',
                        'm.fullname as leader_name',
                    ])
                    ->orderBy('i.created_at', 'desc')
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                return Response::json([
                    'success'             => true,
                    'protocols'           => $protocols,
                    'count'               => count($protocols),
                    'selectedFieldsLabel' => $selectedFieldsLabel,
                ]);
            }

            // Count before delete
            $count = $baseQuery()->count();

            if ($count === 0) {
                return Response::json([
                    'success' => true,
                    'message' => 'Keine passenden Protokolle gefunden',
                    'deleted' => 0,
                ]);
            }

            // Soft-delete via archived=1
            $userId = (int) ($_SESSION['userid'] ?? 0);
            $affectedRows = $baseQuery()->update([
                'i.archived'    => 1,
                'i.archived_at' => Capsule::raw('NOW()'),
                'i.archived_by' => $userId,
                'i.status'      => 4,
                'i.updated_by'  => $userId,
                'i.updated_at'  => Capsule::raw('NOW()'),
            ]);

            $timeLabel   = $timePeriod === 'all' ? 'alle' : "letzte {$timePeriod} Tage";
            $statusLabel = match ($statusFilter) {
                'unfinalized' => ', nur unfertige',
                'finalized'   => ', nur abgeschlossene',
                default       => '',
            };

            (new AuditLogger())->log(
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
