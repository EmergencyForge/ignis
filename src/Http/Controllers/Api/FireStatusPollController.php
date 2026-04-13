<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use DateTime;
use PDO;
use PDOException;

/**
 * FiveM Fire-Status-Polling-Endpoint.
 *
 * Migriert aus `api/emd/status-poll.php`. Wird vom FiveM-Server zyklisch
 * gepollt, um neue Status-Änderungen für Fire-Vehicles abzuholen. Jeder
 * abgeholte Datensatz wird sofort als `delivered=1` markiert (At-most-once
 * Delivery semantik — falls der FiveM-Server zwischendurch crasht, gehen
 * Status-Updates verloren, das ist akzeptiert weil sie eh transient sind).
 *
 * Auth: ApiKeyMiddleware (im Router registriert).
 */
final class FireStatusPollController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function poll(Request $request): Response
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, vehicle_name, new_status, incident_number, created_at
                FROM intra_fire_status_queue
                WHERE delivered = 0
                ORDER BY created_at ASC
            ");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $statusChanges = [];
            $idsToMark     = [];

            foreach ($pending as $row) {
                $createdAt = new DateTime($row['created_at']);
                $statusChanges[] = [
                    'vehicle_name'    => $row['vehicle_name'],
                    'status'          => $row['new_status'],
                    'incident_number' => $row['incident_number'],
                    'timestamp'       => $createdAt->format('d.m.Y H:i'),
                ];
                $idsToMark[] = (int) $row['id'];
            }

            if (!empty($idsToMark)) {
                $placeholders = implode(',', array_fill(0, count($idsToMark), '?'));
                $updateStmt = $this->pdo->prepare(
                    "UPDATE intra_fire_status_queue SET delivered = 1 WHERE id IN ($placeholders)"
                );
                $updateStmt->execute($idsToMark);
            }

            return Response::json([
                'success'        => true,
                'status_changes' => $statusChanges,
            ]);
        } catch (PDOException $e) {
            Logger::error('FireStatusPoll: Datenbankfehler', [
                'error' => $e->getMessage(),
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Datenbankfehler',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
