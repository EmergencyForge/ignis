<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Helpers\MapCoordinates;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use PDO;
use PDOException;

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
    public function __construct(
        private readonly PDO $pdo,
    ) {}

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
        $incidentId  = (int) ($request->post['incident_id'] ?? 0);
        $markerType  = trim($request->post['marker_type'] ?? '');
        $posX        = (float) ($request->post['pos_x'] ?? 0);
        $posY        = (float) ($request->post['pos_y'] ?? 0);
        $description = trim($request->post['description'] ?? '');

        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }
        if ($markerType === '') {
            throw new \InvalidArgumentException('Marker-Typ ist erforderlich');
        }
        if ($posX < 0 || $posX > 100 || $posY < 0 || $posY > 100) {
            throw new \InvalidArgumentException('Ungültige Position');
        }

        $this->assertIncidentEditable($incidentId);
        $this->assertVehicleAssignedOrAdmin($incidentId);

        $userId     = isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null;
        $vehicleId  = isset($request->post['vehicle_id'])
            ? (int) $request->post['vehicle_id']
            : (isset($_SESSION['einsatz_vehicle_id']) ? (int) $_SESSION['einsatz_vehicle_id'] : null);
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $userId    = $this->nullIfNotExists('intra_mitarbeiter', $userId);
        $vehicleId = $this->nullIfNotExists('intra_fahrzeuge', $vehicleId);

        $stmt = $this->pdo->prepare("
            INSERT INTO intra_fire_incident_map_markers
                (incident_id, marker_type, pos_x, pos_y, description,
                 grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text, name,
                 created_by, vehicle_id, operator_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $incidentId, $markerType, $posX, $posY, $description,
            trim($request->post['grundzeichen'] ?? '') ?: null,
            trim($request->post['organisation'] ?? '') ?: null,
            trim($request->post['fachaufgabe'] ?? '')  ?: null,
            trim($request->post['einheit'] ?? '')      ?: null,
            trim($request->post['symbol'] ?? '')       ?: null,
            trim($request->post['typ'] ?? '')          ?: null,
            trim($request->post['text'] ?? '')         ?: null,
            trim($request->post['name'] ?? '')         ?: null,
            $userId, $vehicleId, $operatorId,
        ]);

        $markerId = (int) $this->pdo->lastInsertId();

        $this->logActivity(
            $incidentId, $userId, $vehicleId, $operatorId,
            'marker_created',
            "Lagekarten-Marker hinzugefügt: {$markerType}" . ($description !== '' ? " - {$description}" : '')
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

        $stmt = $this->pdo->prepare(
            "SELECT m.*, i.finalized FROM intra_fire_incident_map_markers m
             JOIN intra_fire_incidents i ON m.incident_id = i.id
             WHERE m.id = ?"
        );
        $stmt->execute([$markerId]);
        $marker = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$marker) {
            throw new \InvalidArgumentException('Marker nicht gefunden');
        }
        if ($marker['finalized']) {
            throw new \InvalidArgumentException('Der Einsatz ist bereits abgeschlossen');
        }

        $this->assertVehicleAssignedOrAdmin((int) $marker['incident_id']);

        $this->pdo->prepare("UPDATE intra_fire_incident_map_markers SET pos_x = ?, pos_y = ? WHERE id = ?")
            ->execute([$posX, $posY, $markerId]);

        return Response::json(['success' => true, 'message' => 'Marker-Position aktualisiert']);
    }

    private function deleteMarker(Request $request): Response
    {
        $markerId = (int) ($request->post['marker_id'] ?? 0);
        if ($markerId <= 0) {
            throw new \InvalidArgumentException('Ungültige Marker-ID');
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.*, i.finalized FROM intra_fire_incident_map_markers m
             JOIN intra_fire_incidents i ON m.incident_id = i.id
             WHERE m.id = ?"
        );
        $stmt->execute([$markerId]);
        $marker = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$marker) {
            throw new \InvalidArgumentException('Marker nicht gefunden');
        }
        if ($marker['finalized']) {
            throw new \InvalidArgumentException('Der Einsatz ist bereits abgeschlossen');
        }

        if (Gate::denies('fireIncident.manageQm')) {
            $userId = $_SESSION['userid'] ?? null;
            if ((int) $marker['created_by'] !== (int) $userId) {
                throw new \InvalidArgumentException('Sie können nur Ihre eigenen Marker löschen');
            }
        }

        $this->pdo->prepare("DELETE FROM intra_fire_incident_map_markers WHERE id = ?")->execute([$markerId]);

        $this->logActivity(
            (int) $marker['incident_id'],
            isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null,
            $_SESSION['einsatz_vehicle_id'] ?? null,
            $_SESSION['einsatz_operator_id'] ?? null,
            'marker_deleted',
            "Lagekarten-Marker gelöscht: {$marker['marker_type']}"
        );

        return Response::json(['success' => true, 'message' => 'Marker erfolgreich gelöscht']);
    }

    private function listMarkers(Request $request): Response
    {
        $incidentId = (int) ($request->query['incident_id'] ?? 0);
        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }

        $stmt = $this->pdo->prepare("
            SELECT m.*,
                   mit.fullname AS created_by_name,
                   v.name       AS vehicle_name,
                   op.fullname  AS operator_name
            FROM intra_fire_incident_map_markers m
            LEFT JOIN intra_mitarbeiter mit ON m.created_by = mit.id
            LEFT JOIN intra_fahrzeuge v ON m.vehicle_id = v.id
            LEFT JOIN intra_mitarbeiter op ON m.operator_id = op.id
            WHERE m.incident_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$incidentId]);

        return Response::json(['success' => true, 'markers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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

        $this->pdo->prepare("
            INSERT INTO intra_fire_incident_map_zones
                (incident_id, name, description, points, color, created_by, vehicle_id, operator_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$incidentId, $name, $description, $points, $color, $userId, $vehicleId, $operatorId]);

        $zoneId = (int) $this->pdo->lastInsertId();

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

        $stmt = $this->pdo->prepare(
            "SELECT z.*, i.finalized FROM intra_fire_incident_map_zones z
             JOIN intra_fire_incidents i ON z.incident_id = i.id
             WHERE z.id = ?"
        );
        $stmt->execute([$zoneId]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$zone) {
            throw new \InvalidArgumentException('Zone nicht gefunden');
        }
        if ($zone['finalized']) {
            throw new \InvalidArgumentException('Einsatz ist bereits abgeschlossen');
        }

        $this->pdo->prepare("DELETE FROM intra_fire_incident_map_zones WHERE id = ?")->execute([$zoneId]);

        $this->logActivity(
            (int) $zone['incident_id'],
            isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null,
            $_SESSION['einsatz_vehicle_id'] ?? null,
            $_SESSION['einsatz_operator_id'] ?? null,
            'zone_deleted',
            "Zone gelöscht: {$zone['name']}"
        );

        return Response::json(['success' => true, 'message' => 'Zone erfolgreich gelöscht']);
    }

    private function listZones(Request $request): Response
    {
        $incidentId = (int) ($request->query['incident_id'] ?? 0);
        if ($incidentId <= 0) {
            throw new \InvalidArgumentException('Ungültige Einsatz-ID');
        }

        $stmt = $this->pdo->prepare("
            SELECT z.*,
                   mit.fullname AS created_by_name,
                   v.name       AS vehicle_name,
                   op.fullname  AS operator_name
            FROM intra_fire_incident_map_zones z
            LEFT JOIN intra_mitarbeiter mit ON z.created_by = mit.id
            LEFT JOIN intra_fahrzeuge v ON z.vehicle_id = v.id
            LEFT JOIN intra_mitarbeiter op ON z.operator_id = op.id
            WHERE z.incident_id = ?
            ORDER BY z.created_at DESC
        ");
        $stmt->execute([$incidentId]);

        return Response::json(['success' => true, 'zones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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

        $stmt = $this->pdo->prepare(
            "SELECT id FROM intra_fire_incident_map_markers
             WHERE incident_id = ? AND marker_type = 'Einsatzort'"
        );
        $stmt->execute([$incidentId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->pdo->prepare(
                "UPDATE intra_fire_incident_map_markers SET pos_x = ?, pos_y = ? WHERE id = ?"
            )->execute([$mapCoords['x'], $mapCoords['y'], $existing['id']]);

            return Response::json([
                'success'    => true,
                'message'    => 'Einsatzort-Marker aktualisiert',
                'marker_id'  => $existing['id'],
                'map_coords' => $mapCoords,
                'gta_coords' => ['x' => $gtaX, 'y' => $gtaY],
            ]);
        }

        $userId     = $_SESSION['userid'] ?? null;
        $vehicleId  = $_SESSION['einsatz_vehicle_id'] ?? null;
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $this->pdo->prepare("
            INSERT INTO intra_fire_incident_map_markers
                (incident_id, marker_type, pos_x, pos_y, description,
                 grundzeichen, organisation, symbol, created_by, vehicle_id, operator_id, created_at)
            VALUES (?, 'Einsatzort', ?, ?, 'Automatisch generiert aus GTA-Koordinaten', 'ohne', NULL, 'feuer', ?, ?, ?, NOW())
        ")->execute([$incidentId, $mapCoords['x'], $mapCoords['y'], $userId, $vehicleId, $operatorId]);

        return Response::json([
            'success'    => true,
            'message'    => 'Einsatzort-Marker erstellt',
            'marker_id'  => (int) $this->pdo->lastInsertId(),
            'map_coords' => $mapCoords,
            'gta_coords' => ['x' => $gtaX, 'y' => $gtaY],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** Wirft, wenn der Einsatz nicht existiert oder bereits abgeschlossen ist. */
    private function assertIncidentEditable(int $incidentId): void
    {
        $stmt = $this->pdo->prepare("SELECT finalized FROM intra_fire_incidents WHERE id = ?");
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            throw new \InvalidArgumentException('Einsatz nicht gefunden');
        }
        if ($incident['finalized']) {
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

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM intra_fire_incident_vehicles
             WHERE incident_id = ? AND vehicle_id = ?"
        );
        $stmt->execute([$incidentId, $_SESSION['einsatz_vehicle_id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new \InvalidArgumentException('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet');
        }
    }

    /** Liefert null wenn die ID nicht in der Tabelle existiert. */
    private function nullIfNotExists(string $table, ?int $id): ?int
    {
        if ($id === null) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return ((int) $stmt->fetchColumn() > 0) ? $id : null;
    }

    /** Aktivitäts-Log — ignoriert Fehler (Tabelle ist optional). */
    private function logActivity(int $incidentId, ?int $userId, mixed $vehicleId, mixed $operatorId, string $actionType, string $description): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO intra_fire_incident_log
                    (incident_id, created_by, vehicle_id, operator_id, action_type, action_description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $incidentId,
                $userId ?: 0,
                $vehicleId,
                $operatorId,
                $actionType,
                $description,
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
        try {
            $exists = $this->pdo->query("SHOW TABLES LIKE 'intra_fire_incident_map_zones'")->rowCount() > 0;

            if ($exists) {
                $hasOldSchema = $this->pdo->query("SHOW COLUMNS FROM intra_fire_incident_map_zones LIKE 'pos_x'")->rowCount() > 0;
                if ($hasOldSchema) {
                    $this->pdo->exec("
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

            $this->pdo->exec("
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
