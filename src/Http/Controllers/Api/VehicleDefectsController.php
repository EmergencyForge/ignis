<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Models\Vehicle;
use App\Models\VehicleDefect;
use App\Models\VehicleDefectLog;
use App\Notifications\NotificationManager;
use Illuminate\Database\Capsule\Manager as Capsule;
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
            $userId = (int) (Capsule::table('intra_users as u')
                ->join('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
                ->where('m.fullname', $reporterName)
                ->value('u.id') ?: 0);
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

        $query = $this->defectBaseQuery(['f.kennzeichen', 'f.veh_type']);

        if ($vehicleId) {
            $query->where('d.vehicle_id', $vehicleId);
        }
        if ($statusFilter && in_array($statusFilter, self::ALLOWED_STATUSES, true)) {
            $query->where('d.status', $statusFilter);
        }

        $defects = $query
            ->orderByRaw(
                "FIELD(d.status, 'open', 'in_progress', 'deferred', 'resolved'),
                 CASE WHEN d.status != 'resolved' THEN d.vehicle_operable END ASC,
                 d.created_at DESC"
            )
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return Response::json(['success' => true, 'defects' => $defects]);
    }

    private function get(Request $request): Response
    {
        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID']);
        }

        $row = $this->defectBaseQuery()
            ->where('d.id', $id)
            ->first();

        if (!$row) {
            return Response::json(['error' => 'Defekt nicht gefunden'], 404);
        }

        $defect        = (array) $row;
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

        $defect = VehicleDefect::create([
            'vehicle_id'       => $data['vehicle_id'],
            'title'            => $data['title'],
            'description'      => $data['description'],
            'category'         => $data['category'],
            'vehicle_operable' => $data['vehicle_operable'],
            'reported_by'      => $userId,
        ]);

        $defectId = (int) $defect->id;

        $logDetails = 'Defekt gemeldet: ' . $data['title'];
        if ($isEnotfUser && !$userId) {
            $logDetails .= ' (Gemeldet durch: ' . $username . ')';
        }
        $this->writeLog($defectId, $userId, 'created', $logDetails);

        if (!$data['vehicle_operable']) {
            Vehicle::query()->where('id', $data['vehicle_id'])->update(['active' => 0]);
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

        $oldRow = Capsule::table('intra_fahrzeuge_defects')
            ->where('id', $id)
            ->first(['status', 'assigned_to']);
        $old = $oldRow !== null ? (array) $oldRow : false;

        $fields      = [];
        $logMessages = [];

        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true) && $status !== ($old['status'] ?? '')) {
            $fields['status'] = $status;
            $oldLabel = self::STATUS_LABELS[$old['status'] ?? 'open'] ?? '?';
            $newLabel = self::STATUS_LABELS[$status] ?? '?';
            $msg = "Status geändert: {$oldLabel} → {$newLabel}";
            if ($statusNote !== '') {
                $msg .= ' | ' . $statusNote;
            }
            $logMessages[] = $msg;
        }

        if ($hasAssignedKey) {
            $fields['assigned_to'] = $assignedTo;

            if ($assignedTo) {
                $assignedName = Capsule::table('intra_users as u')
                    ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
                    ->where('u.id', $assignedTo)
                    ->selectRaw('COALESCE(m.fullname, u.username) AS name')
                    ->value('name');
                $logMessages[] = 'Zugewiesen an: ' . ($assignedName ?: 'Unbekannt');
            } else {
                $logMessages[] = 'Zuweisung entfernt';
            }
        }

        if (empty($fields)) {
            return Response::json(['error' => 'Keine Änderungen']);
        }

        VehicleDefect::query()->where('id', $id)->update($fields);

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

        VehicleDefect::query()
            ->where('id', $id)
            ->update([
                'status'          => 'resolved',
                'resolved_by'     => $userId,
                'resolved_at'     => Capsule::raw('NOW()'),
                'resolution_note' => $note,
            ]);

        $logDetail = 'Als gelöst markiert';
        if ($note !== '') {
            $logDetail .= ': ' . $note;
        }
        $this->writeLog($id, $userId, 'resolved', $logDetail);

        // Prüfen ob das Fahrzeug wieder einsatzfähig ist
        $vehicleId = Capsule::table('intra_fahrzeuge_defects')
            ->where('id', $id)
            ->value('vehicle_id');

        if ($vehicleId !== null) {
            $blockingCount = Capsule::table('intra_fahrzeuge_defects')
                ->where('vehicle_id', $vehicleId)
                ->where('vehicle_operable', 0)
                ->where('status', '!=', 'resolved')
                ->count();
            if ($blockingCount === 0) {
                Vehicle::query()->where('id', $vehicleId)->update(['active' => 1]);
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

        VehicleDefect::query()->where('id', $id)->delete();

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

        $query = Capsule::table('intra_fahrzeuge_defects')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'open'        THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'deferred'    THEN 1 ELSE 0 END) AS deferred_count,
                SUM(CASE WHEN status = 'resolved'    THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN vehicle_operable = 0 AND status != 'resolved' THEN 1 ELSE 0 END) AS not_operable_open
            ");
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        return Response::json(['success' => true, 'stats' => (array) $query->first()]);
    }

    // ── Helper ────────────────────────────────────────────────────────

    /**
     * Basis-Query für Defekt-Listen/-Details: Defekt + Fahrzeug + die
     * per Discord-Tag aufgelösten Anzeigenamen von Melder, Bearbeiter
     * und Löser. Zusätzliche Fahrzeug-Spalten landen — wie im alten
     * SQL — zwischen den Fahrzeug-Basisfeldern und den Namens-Spalten,
     * damit die Feld-Reihenfolge in der JSON-Antwort identisch bleibt.
     *
     * @param array<int, string> $extraVehicleColumns
     */
    private function defectBaseQuery(array $extraVehicleColumns = []): \Illuminate\Database\Query\Builder
    {
        return Capsule::table('intra_fahrzeuge_defects as d')
            ->join('intra_fahrzeuge as f', 'd.vehicle_id', '=', 'f.id')
            ->leftJoin('intra_users as u1', 'd.reported_by', '=', 'u1.id')
            ->leftJoin('intra_mitarbeiter as m1', 'u1.discord_id', '=', 'm1.discordtag')
            ->leftJoin('intra_users as u2', 'd.assigned_to', '=', 'u2.id')
            ->leftJoin('intra_mitarbeiter as m2', 'u2.discord_id', '=', 'm2.discordtag')
            ->leftJoin('intra_users as u3', 'd.resolved_by', '=', 'u3.id')
            ->leftJoin('intra_mitarbeiter as m3', 'u3.discord_id', '=', 'm3.discordtag')
            ->select(array_merge(
                ['d.*', 'f.name as vehicle_name', 'f.identifier as vehicle_identifier'],
                $extraVehicleColumns
            ))
            ->selectRaw('COALESCE(m1.fullname, u1.username) AS reporter_name')
            ->selectRaw('COALESCE(m2.fullname, u2.username) AS assigned_name')
            ->selectRaw('COALESCE(m3.fullname, u3.username) AS resolver_name');
    }

    /** @return list<array<string, mixed>> */
    private function loadLog(int $defectId): array
    {
        return Capsule::table('intra_fahrzeuge_defect_log as l')
            ->leftJoin('intra_users as u', 'l.user_id', '=', 'u.id')
            ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
            ->select('l.*')
            ->selectRaw('COALESCE(m.fullname, u.username) AS user_name')
            ->where('l.defect_id', $defectId)
            ->orderBy('l.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function writeLog(int $defectId, int $userId, string $action, ?string $details = null): void
    {
        VehicleDefectLog::create([
            'defect_id' => $defectId,
            'user_id'   => $userId,
            'action'    => $action,
            'details'   => $details,
        ]);
    }

    /** Benachrichtigt alle User mit vehicles.view oder admin-Permission. */
    private function notifyStaff(int $defectId, int $vehicleId, string $title, bool $operable, int $reporterId): void
    {
        try {
            $vehName = (string) (Capsule::table('intra_fahrzeuge')->where('id', $vehicleId)->value('name') ?: 'Unbekannt');

            $notificationManager = new NotificationManager();

            $users = Capsule::table('intra_users as u')
                ->leftJoin('intra_users_roles as r', 'u.role', '=', 'r.id')
                ->where('u.is_active', 1)
                ->get(['u.id', 'u.full_admin', 'r.permissions'])
                ->map(fn ($row) => (array) $row)
                ->all();

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
