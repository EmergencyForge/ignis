<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Notifications\NotificationManager;
use PDO;
use PDOException;

/**
 * Fahrzeug-Defekte.
 *
 * Action-Dispatcher für die CRUD-Operationen auf `intra_fahrzeuge_defects`.
 * Sonderfall: eNOTF-Besatzungen (Session hat `fahrername` ohne `userid`)
 * dürfen Defekte *erstellen* — für alle anderen Actions gilt die normale
 * Admin-Auth mit Permission `vehicles.manage` bzw. `vehicles.view`.
 *
 * Defekte die das Fahrzeug als nicht einsatzfähig markieren, deaktivieren
 * es automatisch; beim Resolve wird geprüft, ob das Fahrzeug wieder
 * freigegeben werden kann (keine weiteren Sperrungen offen).
 */
final class VehicleDefectsController
{
    /** Erlaubte Defekt-Kategorien — muss identisch zur Legacy-Liste sein. */
    private const ALLOWED_CATEGORIES = [
        'aufbau_karosserie', 'ausbau', 'batterie', 'beleuchtung', 'bremsen',
        'elektrik', 'fahrwerk', 'getriebe', 'motor', 'reifen',
        'service_pruefintervall', 'signalanlage', 'sonstiges', 'windschutzscheibe',
    ];

    private const ALLOWED_STATUSES = ['open', 'in_progress', 'deferred', 'resolved'];

    private const STATUS_LABELS = [
        'open'        => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'deferred'    => 'Aufgeschoben',
        'resolved'    => 'Gelöst',
    ];

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * GET|POST /api/vehicles/defects-handler?action=...
     * Action-Dispatcher, Auth-Handling pro Action individuell.
     */
    public function handle(Request $request): Response
    {
        $action = $request->post['action'] ?? $request->query['action'] ?? '';
        $auth   = $this->resolveAuth($action);
        if ($auth instanceof Response) {
            return $auth;
        }
        [$userId, $username, $isEnotfUser] = $auth;

        try {
            return match ($action) {
                'list'    => $this->list($request),
                'get'     => $this->get($request),
                'create'  => $this->create($request, $userId, $username, $isEnotfUser),
                'update'  => $this->update($request, $userId),
                'resolve' => $this->resolve($request, $userId),
                'delete'  => $this->delete($request),
                'log'     => $this->log($request),
                'stats'   => $this->stats($request),
                default   => Response::json(['error' => 'Unbekannte Aktion'], 400),
            };
        } catch (PDOException $e) {
            Logger::error('VehicleDefects: DB-Fehler', ['action' => $action, 'error' => $e->getMessage()]);
            return Response::json(['error' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * Auth-Resolution pro Action. Gibt entweder eine Response (abbruch) oder
     * ein Tupel [$userId, $username, $isEnotfUser] zurück.
     *
     * @return Response|array{0: int, 1: string, 2: bool}
     */
    private function resolveAuth(string $action): Response|array
    {
        $isEnotfUser = !isset($_SESSION['userid']) && isset($_SESSION['fahrername']);

        if ($isEnotfUser) {
            if ($action !== 'create') {
                return Response::json(['error' => 'Nicht authentifiziert'], 401);
            }
            $reporterName = trim($_POST['reported_by_name'] ?? '') ?: (string) ($_SESSION['fahrername'] ?? '');
            $stmt = $this->pdo->prepare(
                "SELECT u.id FROM intra_users u
                 JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                 WHERE m.fullname = :name LIMIT 1"
            );
            $stmt->execute([':name' => $reporterName]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            return [$userId, $reporterName ?: 'Unbekannt', true];
        }

        if (!isset($_SESSION['userid'])) {
            return Response::json(['error' => 'Nicht authentifiziert'], 401);
        }
        if (Gate::denies('vehicle.view')) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        return [
            (int) $_SESSION['userid'],
            (string) ($_SESSION['cirs_username'] ?? 'Unbekannt'),
            false,
        ];
    }

    // ── Actions ──────────────────────────────────────────────────────

    private function list(Request $request): Response
    {
        $vehicleId    = isset($request->query['vehicle_id']) ? (int) $request->query['vehicle_id'] : null;
        $statusFilter = $request->query['status'] ?? '';

        $sql = "
            SELECT d.*, f.name AS vehicle_name, f.identifier AS vehicle_identifier,
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
            WHERE 1=1
        ";
        $params = [];

        if ($vehicleId) {
            $sql .= " AND d.vehicle_id = :vid";
            $params['vid'] = $vehicleId;
        }
        if ($statusFilter && in_array($statusFilter, self::ALLOWED_STATUSES, true)) {
            $sql .= " AND d.status = :status";
            $params['status'] = $statusFilter;
        }

        $sql .= " ORDER BY FIELD(d.status, 'open', 'in_progress', 'deferred', 'resolved'),
                           CASE WHEN d.status != 'resolved' THEN d.vehicle_operable END ASC,
                           d.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return Response::json(['success' => true, 'defects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function get(Request $request): Response
    {
        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }

        $stmt = $this->pdo->prepare(
            "SELECT d.*, f.name AS vehicle_name, f.identifier AS vehicle_identifier,
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
             WHERE d.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $defect = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$defect) {
            return Response::json(['error' => 'Defekt nicht gefunden'], 404);
        }

        $defect['log'] = $this->loadLog($id);

        return Response::json(['success' => true, 'defect' => $defect]);
    }

    private function create(Request $request, int $userId, string $username, bool $isEnotfUser): Response
    {
        if (!$isEnotfUser && Gate::denies('vehicle.createDefect')) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }
        if (!$userId && !$isEnotfUser) {
            return Response::json(['error' => 'Benutzer konnte nicht zugeordnet werden']);
        }

        // FormRequest-Validation — wirft ValidationException bei Fehlern,
        // die JsonExceptionMiddleware wandelt das in 422 JSON um.
        $data = \App\Http\Requests\Vehicles\CreateDefectRequest::validate($request->post);

        $this->pdo->prepare(
            "INSERT INTO intra_fahrzeuge_defects
                 (vehicle_id, title, description, category, vehicle_operable, reported_by)
             VALUES (:vid, :title, :desc, :cat, :op, :uid)"
        )->execute([
            ':vid'   => $data['vehicle_id'],
            ':title' => $data['title'],
            ':desc'  => $data['description'],
            ':cat'   => $data['category'],
            ':op'    => $data['vehicle_operable'],
            ':uid'   => $userId,
        ]);

        $defectId = (int) $this->pdo->lastInsertId();

        $logDetails = 'Defekt gemeldet: ' . $data['title'];
        if ($isEnotfUser && !$userId) {
            $logDetails .= ' (Gemeldet durch: ' . $username . ')';
        }
        $this->writeLog($defectId, $userId, 'created', $logDetails);

        if (!$data['vehicle_operable']) {
            $this->pdo->prepare("UPDATE intra_fahrzeuge SET active = 0 WHERE id = :id")
                ->execute([':id' => $data['vehicle_id']]);
            $this->writeLog($defectId, $userId, 'vehicle_disabled', 'Fahrzeug als nicht einsatzfähig markiert');
        }

        $this->notifyStaff($defectId, $data['vehicle_id'], $data['title'], (bool) $data['vehicle_operable'], $userId);

        return Response::json(['success' => true, 'id' => $defectId, 'message' => 'Defekt gemeldet']);
    }

    private function update(Request $request, int $userId): Response
    {
        if (Gate::denies('vehicle.manage')) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $id         = (int) ($request->post['id'] ?? 0);
        $status     = (string) ($request->post['status'] ?? '');
        $statusNote = trim($request->post['status_note'] ?? '');
        $hasAssignedKey = array_key_exists('assigned_to', $request->post);
        $assignedTo = ($hasAssignedKey && $request->post['assigned_to'] !== '')
            ? (int) $request->post['assigned_to']
            : null;

        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }

        $oldStmt = $this->pdo->prepare("SELECT status, assigned_to FROM intra_fahrzeuge_defects WHERE id = :id");
        $oldStmt->execute([':id' => $id]);
        $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

        $fields      = [];
        $params      = [':id' => $id];
        $logMessages = [];

        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true) && $status !== ($old['status'] ?? '')) {
            $fields[]           = 'status = :status';
            $params[':status']  = $status;
            $oldLabel = self::STATUS_LABELS[$old['status'] ?? 'open'] ?? '?';
            $newLabel = self::STATUS_LABELS[$status] ?? '?';
            $msg = "Status geändert: {$oldLabel} → {$newLabel}";
            if ($statusNote !== '') {
                $msg .= ' | ' . $statusNote;
            }
            $logMessages[] = $msg;
        }

        if ($hasAssignedKey) {
            $fields[]            = 'assigned_to = :assigned';
            $params[':assigned'] = $assignedTo;

            if ($assignedTo) {
                $nameStmt = $this->pdo->prepare(
                    "SELECT COALESCE(m.fullname, u.username) FROM intra_users u
                     LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
                     WHERE u.id = :id"
                );
                $nameStmt->execute([':id' => $assignedTo]);
                $logMessages[] = 'Zugewiesen an: ' . ($nameStmt->fetchColumn() ?: 'Unbekannt');
            } else {
                $logMessages[] = 'Zuweisung entfernt';
            }
        }

        if (empty($fields)) {
            return Response::json(['error' => 'Keine Änderungen']);
        }

        $this->pdo->prepare("UPDATE intra_fahrzeuge_defects SET " . implode(', ', $fields) . " WHERE id = :id")
            ->execute($params);

        foreach ($logMessages as $msg) {
            $this->writeLog($id, $userId, 'updated', $msg);
        }

        return Response::json(['success' => true, 'message' => 'Defekt aktualisiert']);
    }

    private function resolve(Request $request, int $userId): Response
    {
        if (Gate::denies('vehicle.manage')) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        $id   = (int) ($request->post['id'] ?? 0);
        $note = trim($request->post['resolution_note'] ?? '');
        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }

        $this->pdo->prepare(
            "UPDATE intra_fahrzeuge_defects
             SET status = 'resolved', resolved_by = :uid, resolved_at = NOW(), resolution_note = :note
             WHERE id = :id"
        )->execute([':uid' => $userId, ':note' => $note, ':id' => $id]);

        $logDetail = 'Als gelöst markiert';
        if ($note !== '') {
            $logDetail .= ': ' . $note;
        }
        $this->writeLog($id, $userId, 'resolved', $logDetail);

        // Prüfen ob das Fahrzeug wieder einsatzfähig ist
        $defStmt = $this->pdo->prepare("SELECT vehicle_id FROM intra_fahrzeuge_defects WHERE id = :id");
        $defStmt->execute([':id' => $id]);
        $defect = $defStmt->fetch(PDO::FETCH_ASSOC);

        if ($defect) {
            $cntStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM intra_fahrzeuge_defects
                 WHERE vehicle_id = :vid AND vehicle_operable = 0 AND status != 'resolved'"
            );
            $cntStmt->execute([':vid' => $defect['vehicle_id']]);
            if ((int) $cntStmt->fetchColumn() === 0) {
                $this->pdo->prepare("UPDATE intra_fahrzeuge SET active = 1 WHERE id = :id")
                    ->execute([':id' => $defect['vehicle_id']]);
                $this->writeLog($id, $userId, 'vehicle_enabled', 'Fahrzeug wieder einsatzfähig — keine offenen Sperrungen');
            }
        }

        return Response::json(['success' => true, 'message' => 'Defekt als gelöst markiert']);
    }

    private function delete(Request $request): Response
    {
        if (Gate::denies('vehicle.deleteDefect')) {
            return Response::json(['error' => 'Nur Admins können Defekte löschen'], 403);
        }

        $id = (int) ($request->post['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }

        $this->pdo->prepare("DELETE FROM intra_fahrzeuge_defects WHERE id = :id")->execute([':id' => $id]);

        return Response::json(['success' => true, 'message' => 'Defekt gelöscht']);
    }

    private function log(Request $request): Response
    {
        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }
        return Response::json(['success' => true, 'log' => $this->loadLog($id)]);
    }

    private function stats(Request $request): Response
    {
        $vehicleId = (int) ($request->query['vehicle_id'] ?? 0);

        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'open'        THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'deferred'    THEN 1 ELSE 0 END) AS deferred_count,
                SUM(CASE WHEN status = 'resolved'    THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN vehicle_operable = 0 AND status != 'resolved' THEN 1 ELSE 0 END) AS not_operable_open
            FROM intra_fahrzeuge_defects
        ";
        $params = [];
        if ($vehicleId) {
            $sql .= ' WHERE vehicle_id = :vid';
            $params['vid'] = $vehicleId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return Response::json(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    // ── Helper ────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function loadLog(int $defectId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.*, COALESCE(m.fullname, u.username) AS user_name
             FROM intra_fahrzeuge_defect_log l
             LEFT JOIN intra_users u ON l.user_id = u.id
             LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
             WHERE l.defect_id = :did
             ORDER BY l.created_at ASC"
        );
        $stmt->execute([':did' => $defectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function writeLog(int $defectId, int $userId, string $action, ?string $details = null): void
    {
        $this->pdo->prepare(
            "INSERT INTO intra_fahrzeuge_defect_log (defect_id, user_id, action, details)
             VALUES (:did, :uid, :action, :details)"
        )->execute([
            ':did'     => $defectId,
            ':uid'     => $userId,
            ':action'  => $action,
            ':details' => $details,
        ]);
    }

    /** Benachrichtigt alle User mit vehicles.view oder admin-Permission. */
    private function notifyStaff(int $defectId, int $vehicleId, string $title, bool $operable, int $reporterId): void
    {
        try {
            $vnStmt = $this->pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE id = :id");
            $vnStmt->execute([':id' => $vehicleId]);
            $vehName = (string) ($vnStmt->fetchColumn() ?: 'Unbekannt');

            $notificationManager = new NotificationManager($this->pdo);

            $users = $this->pdo->query(
                "SELECT u.id, u.full_admin, r.permissions
                 FROM intra_users u
                 LEFT JOIN intra_users_roles r ON u.role = r.id
                 WHERE u.is_active = 1"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $u) {
                if ((int) $u['id'] === $reporterId) continue;

                $hasPerm = (bool) $u['full_admin'];
                if (!$hasPerm) {
                    $perms = json_decode((string) ($u['permissions'] ?? '[]'), true);
                    if (is_array($perms) && (in_array('vehicles.view', $perms, true) || in_array('admin', $perms, true))) {
                        $hasPerm = true;
                    }
                }
                if (!$hasPerm) continue;

                $msg = 'Fahrzeug: ' . $vehName;
                if (!$operable) {
                    $msg .= ' — Nicht einsatzfähig!';
                }
                $notificationManager->create(
                    (int) $u['id'],
                    'system',
                    'Neuer Defekt: ' . $title,
                    $msg,
                    (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'settings/vehicles/defects/index'
                );
            }
        } catch (\Throwable $e) {
            Logger::error('VehicleDefects: Benachrichtigungsfehler', ['error' => $e->getMessage()]);
        }
    }
}
