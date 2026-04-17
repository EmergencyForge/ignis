<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use PDO;
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
    public function __construct(
        private readonly PDO $pdo,
    ) {}

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
        $items = $this->pdo->query(
            "SELECT q.* FROM intra_fahrzeuge_import_queue q
             WHERE q.status = 'pending'
             ORDER BY q.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $matchStmt = $this->pdo->prepare(
            "SELECT id, name, identifier, veh_type, rd_type, kennzeichen, priority, active, allowed_jobs
             FROM intra_fahrzeuge
             WHERE name = ? OR identifier = ?
             LIMIT 1"
        );

        foreach ($items as &$item) {
            $matchStmt->execute([$item['name'], $item['identifier']]);
            $existing = $matchStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
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

        $dupCheck = $this->pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE name = ? OR identifier = ?");
        $dupCheck->execute([$item['name'], $item['identifier']]);
        if ($dupCheck->fetch()) {
            return Response::json(['success' => false, 'message' => 'Fahrzeug existiert bereits. Nutze Überschreiben oder Zusammenführen.']);
        }

        $vehType     = $data['veh_type']     ?? trim((string) $item['veh_type']);
        $rdType      = $data['rd_type']      ?? (int) $item['rd_type'];
        $allowedJobs = $data['allowed_jobs'] ?? (trim((string) ($item['job'] ?? '')) ?: null);

        $this->pdo->prepare(
            "INSERT INTO intra_fahrzeuge (name, identifier, veh_type, rd_type, allowed_jobs, priority, active, kennzeichen)
             VALUES (:name, :identifier, :veh_type, :rd_type, :allowed_jobs, 0, 1, '')"
        )->execute([
            ':name'         => $item['name'],
            ':identifier'   => $item['identifier'],
            ':veh_type'     => $vehType,
            ':rd_type'      => $rdType,
            ':allowed_jobs' => $allowedJobs,
        ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger($this->pdo))->log(
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

        $this->pdo->prepare(
            "UPDATE intra_fahrzeuge
             SET name = :name, identifier = :identifier, veh_type = :veh_type,
                 rd_type = :rd_type, allowed_jobs = :allowed_jobs
             WHERE id = :id"
        )->execute([
            ':name'         => $item['name'],
            ':identifier'   => $item['identifier'],
            ':veh_type'     => $vehType,
            ':rd_type'      => $rdType,
            ':allowed_jobs' => $allowedJobs,
            ':id'           => $data['existing_id'],
        ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger($this->pdo))->log(
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

        $existStmt = $this->pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE id = ?");
        $existStmt->execute([$data['existing_id']]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            return Response::json(['success' => false, 'message' => 'Bestehendes Fahrzeug nicht gefunden']);
        }

        $this->pdo->prepare(
            "UPDATE intra_fahrzeuge
             SET identifier = :identifier, veh_type = :veh_type,
                 rd_type = :rd_type, allowed_jobs = :allowed_jobs
             WHERE id = :id"
        )->execute([
            ':identifier'   => !empty($existing['identifier']) ? $existing['identifier'] : $item['identifier'],
            ':veh_type'     => !empty($existing['veh_type'])   ? $existing['veh_type']   : ($item['veh_type'] ?: ''),
            ':rd_type'      => ((int) $existing['rd_type'] > 0) ? $existing['rd_type']    : $item['rd_type'],
            ':allowed_jobs' => !empty($existing['allowed_jobs']) ? $existing['allowed_jobs'] : ($item['job'] ?: null),
            ':id'           => $data['existing_id'],
        ]);

        $this->markProcessed($data['queue_id']);

        (new AuditLogger($this->pdo))->log(
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

        $this->pdo->prepare(
            "UPDATE intra_fahrzeuge_import_queue
             SET status = 'rejected', processed_at = NOW(), processed_by = ?
             WHERE id = ? AND status = 'pending'"
        )->execute([$_SESSION['userid'], $data['queue_id']]);

        return Response::json(['success' => true, 'message' => 'Fahrzeug ignoriert']);
    }

    /** Flag-File setzen, das der FiveM-Server beim nächsten Sync prüft. */
    private function requestImport(): Response
    {
        $flagPath = $this->flagPath();
        @file_put_contents($flagPath, date('Y-m-d H:i:s'));

        (new AuditLogger($this->pdo))->log(
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
        $pendingCount   = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM intra_fahrzeuge_import_queue WHERE status = 'pending'"
        )->fetchColumn();

        return Response::json([
            'success'            => true,
            'request_pending'    => $requestPending,
            'import_queue_count' => $pendingCount,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function loadPendingItem(int $queueId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_fahrzeuge_import_queue WHERE id = ? AND status = 'pending'");
        $stmt->execute([$queueId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    private function markProcessed(int $queueId): void
    {
        $this->pdo->prepare(
            "UPDATE intra_fahrzeuge_import_queue
             SET status = 'accepted', processed_at = NOW(), processed_by = ?
             WHERE id = ?"
        )->execute([$_SESSION['userid'], $queueId]);
    }

    private function flagPath(): string
    {
        // __DIR__ = src/Http/Controllers/Api → 4x dirname() → Projekt-Root
        return dirname(__DIR__, 4) . '/storage/emd_vehicle_import_request.flag';
    }
}
