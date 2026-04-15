<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use PDO;
use PDOException;

/**
 * ASU-Protokoll-Synchronisation (Atemschutz-Überwachungs-Protokoll).
 *
 * Wird vom FiveM-Server als Machine-to-Machine-Call gerufen, wenn ein
 * ASU-Protokoll an intraRP übermittelt wird. Auth per ApiKeyMiddleware;
 * im Controller wird danach nur noch die Business-Logik abgearbeitet.
 *
 * Erwarteter Payload:
 *   {
 *     "type": "asu_protocol",
 *     "data": {
 *         "missionNumber": "...",
 *         "supervisor":    "...",
 *         "missionLocation": "...",
 *         "missionDate":     "DD.MM.YYYY",
 *         "timestamp":       "ISO 8601",
 *         ...
 *     }
 *   }
 */
final class AsuSyncController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/asu/sync
     */
    public function sync(Request $request): Response
    {
        $received = $request->json();
        if (!is_array($received)) {
            Logger::error('AsuSync: Ungültiges JSON empfangen');
            return Response::json(['success' => false, 'error' => 'Ungültiges JSON'], 400);
        }

        if (!isset($received['type']) || $received['type'] !== 'asu_protocol') {
            Logger::warning('AsuSync: Ungültiger oder fehlender Sync-Typ', [
                'type' => $received['type'] ?? 'none',
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Ungültiger oder fehlender Sync-Typ',
            ], 400);
        }

        return $this->handleAsuProtocol($received);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleAsuProtocol(array $data): Response
    {
        $protocolData = $data['data'] ?? [];

        if (empty($protocolData)) {
            Logger::info('AsuSync: Keine ASU-Daten in der Anfrage');
            return Response::json([
                'success'   => true,
                'message'   => 'Keine ASU-Daten zu verarbeiten',
                'processed' => 0,
            ]);
        }

        $missionNumber = $protocolData['missionNumber'] ?? null;
        $supervisor    = $protocolData['supervisor']    ?? null;

        if (!$missionNumber || !$supervisor) {
            Logger::warning('AsuSync: Erforderliche Felder fehlen', [
                'has_mission_number' => !empty($missionNumber),
                'has_supervisor'     => !empty($supervisor),
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Erforderliche Felder fehlen',
                'message' => 'missionNumber und supervisor sind erforderlich',
            ], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM intra_fire_incidents WHERE incident_number = ? LIMIT 1"
            );
            $stmt->execute([$missionNumber]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$incident) {
                Logger::warning('AsuSync: Einsatz nicht gefunden', ['mission_number' => $missionNumber]);
                return Response::json([
                    'success' => false,
                    'error'   => 'Einsatz nicht gefunden',
                    'message' => "Kein Einsatz mit Nummer '$missionNumber' gefunden",
                ], 404);
            }

            $incidentId = (int) $incident['id'];
            Logger::info('AsuSync: Einsatz gefunden', ['incident_id' => $incidentId, 'supervisor' => $supervisor]);

            $this->pdo->beginTransaction();

            // Prüfe, ob bereits ein ASU-Protokoll für diesen Überwacher existiert
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM intra_fire_incident_asu
                WHERE incident_id = ? AND supervisor = ?
                LIMIT 1
            ");
            $checkStmt->execute([$incidentId, $supervisor]);
            $existingAsu = $checkStmt->fetch(PDO::FETCH_ASSOC);

            $missionLocation = $protocolData['missionLocation'] ?? null;
            $missionDate     = $this->normalizeMissionDate($protocolData['missionDate'] ?? null);
            $timestamp       = $this->normalizeTimestamp($protocolData['timestamp'] ?? null);

            if ($existingAsu) {
                $this->pdo->prepare("
                    UPDATE intra_fire_incident_asu
                    SET mission_location = ?, mission_date = ?, timestamp = ?, data = ?
                    WHERE id = ?
                ")->execute([
                    $missionLocation,
                    $missionDate,
                    $timestamp,
                    json_encode($protocolData),
                    $existingAsu['id'],
                ]);
                $action = 'updated';
            } else {
                $this->pdo->prepare("
                    INSERT INTO intra_fire_incident_asu
                    (incident_id, supervisor, mission_location, mission_date, timestamp, data)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $incidentId,
                    $supervisor,
                    $missionLocation,
                    $missionDate,
                    $timestamp,
                    json_encode($protocolData),
                ]);
                $action = 'created';
            }

            $this->pdo->commit();

            Logger::info('AsuSync: ASU-Protokoll verarbeitet', [
                'action'      => $action,
                'incident_id' => $incidentId,
                'supervisor'  => $supervisor,
            ]);

            return Response::json([
                'success'        => true,
                'message'        => 'ASU-Protokoll erfolgreich verarbeitet',
                'incident_id'    => $incidentId,
                'mission_number' => $missionNumber,
                'supervisor'     => $supervisor,
                'action'         => $action,
            ]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('AsuSync: Datenbankfehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'error'   => 'Datenbankfehler',
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('AsuSync: Verarbeitungsfehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'error'   => 'Verarbeitungsfehler',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Konvertiert ein deutsches Datum (DD.MM.YYYY) in MySQL-kompatibles
     * YYYY-MM-DD. Gibt null zurück wenn $input null/leer ist.
     */
    private function normalizeMissionDate(?string $input): ?string
    {
        if (!$input) {
            return null;
        }
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $input, $m)) {
            return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        return $input;
    }

    /**
     * Konvertiert ISO 8601 Timestamp ("2025-04-13T06:38:43.000Z") in
     * MySQL DATETIME ("2025-04-13 06:38:43").
     */
    private function normalizeTimestamp(?string $input): string
    {
        $fallback = date('Y-m-d H:i:s');
        if (!$input) {
            return $fallback;
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $input, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
        }
        return $fallback;
    }
}
