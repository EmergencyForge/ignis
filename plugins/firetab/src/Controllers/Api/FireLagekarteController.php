<?php

declare(strict_types=1);

namespace Plugin\Firetab\Controllers\Api;

use App\Auth\Gate;
use App\Helpers\MapCoordinates;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\Firetab\Models\FireIncident;
use Plugin\Firetab\Models\FireIncidentLogEntry;
use Plugin\Firetab\Models\FireIncidentVehicle;
use Plugin\Firetab\Models\FireMapMarker;
use Plugin\Firetab\Models\FireMapZone;

/**
 * Lagekarte für Feuerwehr-Einsätze.
 *
 * Verwaltet taktische Marker und Zonen auf der Einsatz-Lagekarte
 * (`intra_fire_incident_map_markers` + `intra_fire_incident_map_zones`).
 * Permissions:
 *   - Admin und `fire.incident.qm` dürfen alles
 *   - Reguläre Crews dürfen nur Marker/Zonen zum Einsatz ihres aktuell
 *     angemeldeten Fahrzeugs anlegen; Marker löschen dürfen sie nur
 *     ihre eigenen
 */
final class FireLagekarteController
{
    /**
     * GET|POST /api/fire/lagekarte?action=...
     * Action-Dispatcher.
     */
    public function handle(Request $request): Response
    {
        $action = $request->post['action'] ?? $request->query['action'] ?? '';

        try {
            return match ($action) {
                'create'                          => $this->createMarker($request),
                'update'                          => $this->updateMarker($request),
                'delete'                          => $this->deleteMarker($request),
                'list'                            => $this->listMarkers($request),
                'create_zone'                     => $this->createZone($request),
                'delete_zone'                     => $this->deleteZone($request),
                'list_zones'                      => $this->listZones($request),
                'create_incident_location_marker' => $this->createIncidentLocationMarker($request),
                default                           => Response::json(['success' => false, 'error' => 'Ungültige Aktion'], 400),
            };
        } catch (\InvalidArgumentException $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (PDOException $e) {
            Logger::error('FireLagekarte: DB-Fehler', ['action' => $action, 'error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Datenbankfehler'], 500);
        } catch (\Throwable $e) {
            Logger::error('FireLagekarte: Unerwartet', ['action' => $action, 'error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Marker ────────────────────────────────────────────────────────

    private function createMarker(Request $request): Response
    {
        // FormRequest-Validation — wirft ValidationException → JsonExceptionMiddleware
        // wandelt in 422 JSON. Der Controller ist danach frei von Input-Checks.
        $data = \Plugin\Firetab\Requests\Fire\CreateMarkerRequest::validate($request->post);

        $this->assertIncidentEditable($data['incident_id']);
        $this->assertVehicleAssignedOrAdmin($data['incident_id']);

        $userId     = isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null;
        $vehicleId  = $data['vehicle_id']
            ?? (isset($_SESSION['einsatz_vehicle_id']) ? (int) $_SESSION['einsatz_vehicle_id'] : null);
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $userId    = $this->nullIfNotExists('intra_mitarbeiter', $userId);
        $vehicleId = $this->nullIfNotExists('intra_fahrzeuge', $vehicleId);

        $marker = FireMapMarker::create([
            'incident_id'  => $data['incident_id'],
            'marker_type'  => $data['marker_type'],
            'pos_x'        => $data['pos_x'],
            'pos_y'        => $data['pos_y'],
            'description'  => $data['description'],
            'grundzeichen' => $data['grundzeichen'],
            'organisation' => $data['organisation'],
            'fachaufgabe'  => $data['fachaufgabe'],
            'einheit'      => $data['einheit'],
            'symbol'       => $data['symbol'],
            'typ'          => $data['typ'],
            'text'         => $data['text'],
            'name'         => $data['name'],
            'created_by'   => $userId,
            'vehicle_id'   => $vehicleId,
            'operator_id'  => $operatorId,
            'created_at'   => Capsule::raw('NOW()'),
        ]);

        $markerId = (int) $marker->id;

        $this->logActivity(
            $data['incident_id'], $userId, $vehicleId, $operatorId,
            'marker_created',
            "Lagekarten-Marker hinzugefügt: {$data['marker_type']}" . ($data['description'] !== '' ? " - {$data['description']}" : '')
        );

        return Response::json([
            'success'   => true,
            'marker_id' => $markerId,
            'message'   => 'Marker erfolgreich erstellt',
        ]);
    }

    private function updateMarker(Request $request): Response
    {
        $markerId = (int) ($request->post['marker_id'] ?? 0);
        $posX     = (float) ($request->post['pos_x'] ?? -1);
        $posY     = (float) ($request->post['pos_y'] ?? -1);

        if ($markerId <= 0) {
            throw new \InvalidArgumentException('Ungültige Marker-ID');
        }
        if ($posX < 0 || $posX > 100 || $posY < 0 || $posY > 100) {
            throw new \InvalidArgumentException('Ungültige Position');
        }

        $marker = Capsule::table('intra_fire_incident_map_markers as m')
            ->join('intra_fire_incidents as i', 'm.incident_id', '=', 'i.id')
            ->where('m.id', $markerId)
            ->select(['m.*', 'i.finalized'])
            ->first();

        if (!$marker) {
            throw new \InvalidArgumentException('Marker nicht gefunden');
        }
        if ($marker->finalized) {
            throw new \InvalidArgumentException('Der Einsatz ist bereits abgeschlossen');
        }

        $this->assertVehicleAssignedOrAdmin((int) $marker->incident_id);

        FireMapMarker::where('id', $markerId)->update(['pos_x' => $posX, 'pos_y' => $posY]);

        return Response::json(['success' => true, 'message' => 'Marker-Position aktualisiert']);
    }

    private function deleteMarker(Request $request): Response
    {
        $markerId = (int) ($request->post['marker_id'] ?? 0);
        if ($markerId <= 0) {
            throw new \InvalidArgumentException('Ungültige Marker-ID');
        }

        $marker = Capsule::table('intra_fire_incident_map_markers as m')
            ->join('intra_fire_incidents as i', 'm.incident_id', '=', 'i.id')
            ->where('m.id', $markerId)
            ->select(['m.*', 'i.finalized'])
            ->first();

        if (!$marker) {
            throw new \InvalidArgumentException('Marker nicht gefunden');
        }
        if ($marker->finalized) {
            throw new \InvalidArgumentException('Der Einsatz ist bereits abgeschlossen');
        }

        if (Gate::denies('fireIncident.manageQm')) {
            $userId = $_SESSION['userid'] ?? null;
            if ((int) $marker->created_by !== (int) $userId) {
                throw new \InvalidArgumentException('Sie können nur Ihre eigenen Marker löschen');
            }
        }

        FireMapMarker::where('id', $markerId)->delete();

        $this->logActivity(
            (int) $marker->incident_id,
            isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null,
            $_SESSION['einsatz_vehicle_id'] ?? null,
            $_SESSION['einsatz_operator_id'] ?? null,
            'marker_deleted',
            "Lagekarten-Marker gelöscht: {$marker->marker_type}"
        );

        return Response::json(['success' => true, 'message' => 'Marker erfolgreich gelöscht']);
    }

    private function listMarkers(Request $request): Response
    {
        $incidentId = (int) ($request->query['incident_id'] ?? 0);
        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }

        $markers = Capsule::table('intra_fire_incident_map_markers as m')
            ->leftJoin('intra_mitarbeiter as mit', 'm.created_by', '=', 'mit.id')
            ->leftJoin('intra_fahrzeuge as v', 'm.vehicle_id', '=', 'v.id')
            ->leftJoin('intra_mitarbeiter as op', 'm.operator_id', '=', 'op.id')
            ->where('m.incident_id', $incidentId)
            ->select([
                'm.*',
                'mit.fullname as created_by_name',
                'v.name as vehicle_name',
                'op.fullname as operator_name',
            ])
            ->orderBy('m.created_at', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return Response::json(['success' => true, 'markers' => $markers]);
    }

    // ── Zonen ─────────────────────────────────────────────────────────

    private function createZone(Request $request): Response
    {
        $incidentId  = (int) ($request->post['incident_id'] ?? 0);
        $name        = trim($request->post['name'] ?? '');
        $description = trim($request->post['description'] ?? '');
        $points      = trim($request->post['points'] ?? '');
        $color       = trim($request->post['color'] ?? '#dc3545');

        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Zonenname ist erforderlich');
        }
        if ($points === '') {
            throw new \InvalidArgumentException('Zonenpunkte fehlen');
        }

        $pointsArray = json_decode($points, true);
        if (!is_array($pointsArray) || count($pointsArray) < 3) {
            throw new \InvalidArgumentException('Mindestens 3 Punkte erforderlich');
        }

        $this->assertIncidentEditable($incidentId);
        $this->ensureZonesTable();

        $userId     = $this->nullIfNotExists('intra_mitarbeiter', isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null);
        $vehicleId  = $this->nullIfNotExists('intra_fahrzeuge', isset($_SESSION['einsatz_vehicle_id']) ? (int) $_SESSION['einsatz_vehicle_id'] : null);
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $zone = FireMapZone::create([
            'incident_id' => $incidentId,
            'name'        => $name,
            'description' => $description,
            'points'      => $points,
            'color'       => $color,
            'created_by'  => $userId,
            'vehicle_id'  => $vehicleId,
            'operator_id' => $operatorId,
            'created_at'  => Capsule::raw('NOW()'),
        ]);

        $zoneId = (int) $zone->id;

        $this->logActivity($incidentId, $userId, $vehicleId, $operatorId, 'zone_created', "Zone erstellt: {$name}");

        return Response::json([
            'success' => true,
            'zone_id' => $zoneId,
            'message' => 'Zone erfolgreich erstellt',
        ]);
    }

    private function deleteZone(Request $request): Response
    {
        $zoneId = (int) ($request->post['zone_id'] ?? 0);
        if ($zoneId <= 0) {
            throw new \InvalidArgumentException('Ungültige Zonen-ID');
        }

        $zone = Capsule::table('intra_fire_incident_map_zones as z')
            ->join('intra_fire_incidents as i', 'z.incident_id', '=', 'i.id')
            ->where('z.id', $zoneId)
            ->select(['z.*', 'i.finalized'])
            ->first();

        if (!$zone) {
            throw new \InvalidArgumentException('Zone nicht gefunden');
        }
        if ($zone->finalized) {
            throw new \InvalidArgumentException('Einsatz ist bereits abgeschlossen');
        }

        FireMapZone::where('id', $zoneId)->delete();

        $this->logActivity(
            (int) $zone->incident_id,
            isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null,
            $_SESSION['einsatz_vehicle_id'] ?? null,
            $_SESSION['einsatz_operator_id'] ?? null,
            'zone_deleted',
            "Zone gelöscht: {$zone->name}"
        );

        return Response::json(['success' => true, 'message' => 'Zone erfolgreich gelöscht']);
    }

    private function listZones(Request $request): Response
    {
        $incidentId = (int) ($request->query['incident_id'] ?? 0);
        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }

        $zones = Capsule::table('intra_fire_incident_map_zones as z')
            ->leftJoin('intra_mitarbeiter as mit', 'z.created_by', '=', 'mit.id')
            ->leftJoin('intra_fahrzeuge as v', 'z.vehicle_id', '=', 'v.id')
            ->leftJoin('intra_mitarbeiter as op', 'z.operator_id', '=', 'op.id')
            ->where('z.incident_id', $incidentId)
            ->select([
                'z.*',
                'mit.fullname as created_by_name',
                'v.name as vehicle_name',
                'op.fullname as operator_name',
            ])
            ->orderBy('z.created_at', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return Response::json(['success' => true, 'zones' => $zones]);
    }

    /**
     * Erstellt oder aktualisiert den automatischen Einsatzort-Marker
     * anhand der GTA-Koordinaten aus `intra_fire_incidents.location_x/y`.
     */
    private function createIncidentLocationMarker(Request $request): Response
    {
        $incidentId = (int) ($request->post['incident_id'] ?? 0);
        $gtaX       = (float) ($request->post['gta_x'] ?? 0);
        $gtaY       = (float) ($request->post['gta_y'] ?? 0);

        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }

        $mapCoords = MapCoordinates::gtaToMap($gtaX, $gtaY);

        $existing = FireMapMarker::where('incident_id', $incidentId)
            ->where('marker_type', 'Einsatzort')
            ->first();

        if ($existing) {
            FireMapMarker::where('id', $existing->id)
                ->update(['pos_x' => $mapCoords['x'], 'pos_y' => $mapCoords['y']]);

            return Response::json([
                'success'    => true,
                'message'    => 'Einsatzort-Marker aktualisiert',
                'marker_id'  => $existing->id,
                'map_coords' => $mapCoords,
                'gta_coords' => ['x' => $gtaX, 'y' => $gtaY],
            ]);
        }

        $userId     = $_SESSION['userid'] ?? null;
        $vehicleId  = $_SESSION['einsatz_vehicle_id'] ?? null;
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $marker = FireMapMarker::create([
            'incident_id'  => $incidentId,
            'marker_type'  => 'Einsatzort',
            'pos_x'        => $mapCoords['x'],
            'pos_y'        => $mapCoords['y'],
            'description'  => 'Automatisch generiert aus GTA-Koordinaten',
            'grundzeichen' => 'ohne',
            'organisation' => null,
            'symbol'       => 'feuer',
            'created_by'   => $userId,
            'vehicle_id'   => $vehicleId,
            'operator_id'  => $operatorId,
            'created_at'   => Capsule::raw('NOW()'),
        ]);

        return Response::json([
            'success'    => true,
            'message'    => 'Einsatzort-Marker erstellt',
            'marker_id'  => (int) $marker->id,
            'map_coords' => $mapCoords,
            'gta_coords' => ['x' => $gtaX, 'y' => $gtaY],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** Wirft, wenn der Einsatz nicht existiert oder bereits abgeschlossen ist. */
    private function assertIncidentEditable(int $incidentId): void
    {
        $incident = FireIncident::find($incidentId);

        if (!$incident) {
            throw new \InvalidArgumentException('Einsatz nicht gefunden');
        }
        if ($incident->finalized) {
            throw new \InvalidArgumentException('Dieser Einsatz ist bereits abgeschlossen');
        }
    }

    /**
     * Reguläre Crews dürfen nur am Einsatz ihres angemeldeten Fahrzeugs
     * arbeiten. Admins + QM umgehen den Check.
     */
    private function assertVehicleAssignedOrAdmin(int $incidentId): void
    {
        if (Gate::allows('fireIncident.manageQm')) {
            return;
        }
        if (!isset($_SESSION['einsatz_vehicle_id'])) {
            throw new \InvalidArgumentException('Kein Fahrzeug angemeldet');
        }

        $assigned = FireIncidentVehicle::where('incident_id', $incidentId)
            ->where('vehicle_id', $_SESSION['einsatz_vehicle_id'])
            ->count();
        if ($assigned === 0) {
            throw new \InvalidArgumentException('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet');
        }
    }

    /** Liefert null wenn die ID nicht in der Tabelle existiert. */
    private function nullIfNotExists(string $table, ?int $id): ?int
    {
        if ($id === null) {
            return null;
        }
        return Capsule::table($table)->where('id', $id)->exists() ? $id : null;
    }

    /** Aktivitäts-Log — ignoriert Fehler (Tabelle ist optional). */
    private function logActivity(int $incidentId, ?int $userId, mixed $vehicleId, mixed $operatorId, string $actionType, string $description): void
    {
        try {
            FireIncidentLogEntry::create([
                'incident_id'        => $incidentId,
                'created_by'         => $userId ?: 0,
                'vehicle_id'         => $vehicleId,
                'operator_id'        => $operatorId,
                'action_type'        => $actionType,
                'action_description' => $description,
                'created_at'         => Capsule::raw('NOW()'),
            ]);
        } catch (PDOException $e) {
            // Log-Tabelle ist optional — Fehler ignorieren
        }
    }

    /**
     * Historisches Schema-Update für `intra_fire_incident_map_zones`.
     * Wenn die Tabelle noch das alte Rect-Schema (pos_x/pos_y/width/height)
     * nutzt, wird sie inline auf Polygon (points TEXT) umgezogen.
     * Sollte auf aktuellen Installationen ein no-op sein.
     */
    private function ensureZonesTable(): void
    {
        $connection = Capsule::connection();

        try {
            $exists = count($connection->select("SHOW TABLES LIKE 'intra_fire_incident_map_zones'")) > 0;

            if ($exists) {
                $hasOldSchema = count($connection->select("SHOW COLUMNS FROM intra_fire_incident_map_zones LIKE 'pos_x'")) > 0;
                if ($hasOldSchema) {
                    $connection->statement("
                        ALTER TABLE intra_fire_incident_map_zones
                        DROP COLUMN pos_x,
                        DROP COLUMN pos_y,
                        DROP COLUMN width,
                        DROP COLUMN height,
                        ADD COLUMN points TEXT NOT NULL AFTER description
                    ");
                }
                return;
            }

            $connection->statement("
                CREATE TABLE intra_fire_incident_map_zones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    incident_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    points TEXT NOT NULL,
                    color VARCHAR(20) NOT NULL DEFAULT '#dc3545',
                    created_by INT,
                    vehicle_id INT,
                    operator_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (incident_id) REFERENCES intra_fire_incidents(id) ON DELETE CASCADE
                )
            ");
        } catch (PDOException $e) {
            Logger::error('FireLagekarte: Zones-Schema-Check fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
