<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Models\Vehicle;
use App\Models\VehicleImportQueueItem;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * EMD Fahrzeug-Import.
 *
 * Verwaltet die Import-Queue für Fahrzeuge aus dem EMD (Emergency
 * Management Dashboard / FiveM-Server). Der FiveM-Server schreibt
 * neu erkannte Fahrzeuge in `intra_fahrzeuge_import_queue`; Admins
 * entscheiden hier, ob ein Eintrag importiert, mit einem
 * bestehenden Fahrzeug zusammengeführt, überschrieben oder
 * ignoriert wird.
 */
final class VehicleImportController
{
    /**
     * GET|POST /api/vehicles/import-handler?action=...
     * Action-Dispatcher für alle Import-Queue-Operationen.
     */
    public function handle(Request $request): Response
    {
        if (!isset($_SESSION['userid'], $_SESSION['permissions'])) {
            return Response::json(['success' => false, 'message' => 'Nicht authentifiziert']);
        }
        if (Gate::denies('vehicle.manageImport')) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung']);
        }

        $action = $request->query['action'] ?? $request->post['action'] ?? '';
        $method = strtoupper($request->method);

        try {
            return match (true) {
                $action === 'list'                         => $this->listPending(),
                $action === 'import'    && $method === 'POST' => $this->importNew($request),
                $action === 'overwrite' && $method === 'POST' => $this->overwriteExisting($request),
                $action === 'merge'     && $method === 'POST' => $this->mergeWithExisting($request),
                $action === 'ignore'    && $method === 'POST' => $this->ignore($request),
                $action === 'request'                      => $this->requestImport(),
                $action === 'status'                       => $this->status(),
                default                                    => Response::json(['success' => false, 'message' => 'Unbekannte Aktion']),
            };
        } catch (PDOException $e) {
            Logger::error('VehicleImport: DB-Fehler', ['action' => $action, 'error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /** Pending-Fahrzeuge mit Match-Info gegen bestehende intra_fahrzeuge laden. */
    private function listPending(): Response
    {
        $items = Capsule::table('intra_fahrzeuge_import_queue')
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach ($items as &$item) {
            $existing = Capsule::table('intra_fahrzeuge')
                ->select(['id', 'name', 'identifier', 'veh_type', 'rd_type', 'kennzeichen', 'priority', 'active', 'allowed_jobs'])
                ->where('name', $item['name'])
                ->orWhere('identifier', $item['identifier'])
                ->first();

            if ($existing) {
                $existing           = (array) $existing;
                $item['existing']   = $existing;
                $item['match_type'] = ($existing['name'] === $item['name']) ? 'name' : 'identifier';
            } else {
                $item['existing']   = null;
                $item['match_type'] = null;
            }
        }
        unset($item);

        return Response::json([
            'success'  => true,
            'vehicles' => $items,
            'count'    => count($items),
        ]);
    }

    /** Neues Fahrzeug aus einem Queue-Eintrag anlegen. */
    private function importNew(Request $request): Response
    {
        $data = \App\Http\Requests\Vehicles\ImportQueueItemRequest::validate($request->post);

        $item = $this->loadPendingItem($data['queue_id']);
        if (!$item) {
            return Response::json(['success' => false, 'message' => 'Eintrag nicht gefunden oder bereits verarbeitet']);
        }

        $duplicateExists = Vehicle::query()
            ->where('name', $item['name'])
            ->orWhere('identifier', $item['identifier'])
            ->exists();
        if ($duplicateExists) {
            return Response::json(['success' => false, 'message' => 'Fahrzeug existiert bereits. Nutze Überschreiben oder Zusammenführen.']);
        }

        $vehType     = $data['veh_type']     ?? trim((string) $item['veh_type']);
        $rdType      = $data['rd_type']      ?? (int) $item['rd_type'];
        $allowedJobs = $data['allowed_jobs'] ?? (trim((string) ($item['job'] ?? '')) ?: null);

        Vehicle::create([
            'name'         => $item['name'],
            'identifier'   => $item['identifier'],
            'veh_type'     => $vehType,
            'rd_type'      => $rdType,
            'allowed_jobs' => $allowedJobs,
            'priority'     => 0,
            'active'       => 1,
            'kennzeichen'  => '',
        ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger())->log(
            (int) $_SESSION['userid'],
            'Fahrzeug per EMD-Import erstellt',
            "Name: {$item['name']} | Typ: {$vehType}",
            'Fahrzeuge',
            1
        );

        return Response::json(['success' => true, 'message' => "'{$item['name']}' importiert"]);
    }

    /** Bestehendes Fahrzeug durch Queue-Daten ersetzen. */
    private function overwriteExisting(Request $request): Response
    {
        $data = \App\Http\Requests\Vehicles\ImportQueueItemRequest::validate($request->post);
        if ($data['existing_id'] === null) {
            return Response::json(['success' => false, 'message' => 'Existing-ID fehlt.']);
        }

        $item = $this->loadPendingItem($data['queue_id']);
        if (!$item) {
            return Response::json(['success' => false, 'message' => 'Eintrag nicht gefunden']);
        }

        $vehType     = $data['veh_type']     ?? trim((string) $item['veh_type']);
        $rdType      = $data['rd_type']      ?? (int) $item['rd_type'];
        $allowedJobs = $data['allowed_jobs'] ?? (trim((string) ($item['job'] ?? '')) ?: null);

        Vehicle::query()
            ->where('id', $data['existing_id'])
            ->update([
                'name'         => $item['name'],
                'identifier'   => $item['identifier'],
                'veh_type'     => $vehType,
                'rd_type'      => $rdType,
                'allowed_jobs' => $allowedJobs,
            ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger())->log(
            (int) $_SESSION['userid'],
            'Fahrzeug per EMD-Import überschrieben',
            "Name: {$item['name']} | ID: {$data['existing_id']}",
            'Fahrzeuge',
            1
        );

        return Response::json(['success' => true, 'message' => "'{$item['name']}' überschrieben"]);
    }

    /** Queue-Daten in bestehendes Fahrzeug zusammenführen (nur leere Felder). */
    private function mergeWithExisting(Request $request): Response
    {
        $data = \App\Http\Requests\Vehicles\ImportQueueItemRequest::validate($request->post);
        if ($data['existing_id'] === null) {
            return Response::json(['success' => false, 'message' => 'Existing-ID fehlt.']);
        }

        $item = $this->loadPendingItem($data['queue_id']);
        if (!$item) {
            return Response::json(['success' => false, 'message' => 'Eintrag nicht gefunden']);
        }

        $existingRow = Capsule::table('intra_fahrzeuge')
            ->where('id', $data['existing_id'])
            ->first();

        if (!$existingRow) {
            return Response::json(['success' => false, 'message' => 'Bestehendes Fahrzeug nicht gefunden']);
        }
        $existing = (array) $existingRow;

        Vehicle::query()
            ->where('id', $data['existing_id'])
            ->update([
                'identifier'   => !empty($existing['identifier']) ? $existing['identifier'] : $item['identifier'],
                'veh_type'     => !empty($existing['veh_type'])   ? $existing['veh_type']   : ($item['veh_type'] ?: ''),
                'rd_type'      => ((int) $existing['rd_type'] > 0) ? $existing['rd_type']    : $item['rd_type'],
                'allowed_jobs' => !empty($existing['allowed_jobs']) ? $existing['allowed_jobs'] : ($item['job'] ?: null),
            ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger())->log(
            (int) $_SESSION['userid'],
            'Fahrzeug per EMD-Import zusammengeführt',
            "Name: {$item['name']} | ID: {$data['existing_id']}",
            'Fahrzeuge',
            1
        );

        return Response::json(['success' => true, 'message' => "'{$item['name']}' zusammengeführt"]);
    }

    private function ignore(Request $request): Response
    {
        $data = \App\Http\Requests\Vehicles\ImportQueueItemRequest::validate($request->post);

        VehicleImportQueueItem::query()
            ->where('id', $data['queue_id'])
            ->where('status', 'pending')
            ->update([
                'status'       => 'rejected',
                'processed_at' => Capsule::raw('NOW()'),
                'processed_by' => $_SESSION['userid'],
            ]);

        return Response::json(['success' => true, 'message' => 'Fahrzeug ignoriert']);
    }

    /** Flag-File setzen, das der FiveM-Server beim nächsten Sync prüft. */
    private function requestImport(): Response
    {
        $flagPath = $this->flagPath();
        @file_put_contents($flagPath, date('Y-m-d H:i:s'));

        (new AuditLogger())->log(
            (int) $_SESSION['userid'],
            'EMD Fahrzeug-Import angefordert',
            'Flag gesetzt - wird beim nächsten Sync übermittelt',
            'Fahrzeuge',
            1
        );

        return Response::json([
            'success' => true,
            'message' => 'Fahrzeug-Import angefordert. Die Daten werden beim nächsten EMD-Sync übermittelt.',
        ]);
    }

    private function status(): Response
    {
        $requestPending = file_exists($this->flagPath());
        $pendingCount   = VehicleImportQueueItem::query()->pending()->count();

        return Response::json([
            'success'            => true,
            'request_pending'    => $requestPending,
            'import_queue_count' => $pendingCount,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function loadPendingItem(int $queueId): ?array
    {
        $item = Capsule::table('intra_fahrzeuge_import_queue')
            ->where('id', $queueId)
            ->where('status', 'pending')
            ->first();

        return $item !== null ? (array) $item : null;
    }

    private function markProcessed(int $queueId): void
    {
        VehicleImportQueueItem::query()
            ->where('id', $queueId)
            ->update([
                'status'       => 'accepted',
                'processed_at' => Capsule::raw('NOW()'),
                'processed_by' => $_SESSION['userid'],
            ]);
    }

    private function flagPath(): string
    {
        // __DIR__ = src/Http/Controllers/Api → 4x dirname() → Projekt-Root
        return dirname(__DIR__, 4) . '/storage/emd_vehicle_import_request.flag';
    }
}
