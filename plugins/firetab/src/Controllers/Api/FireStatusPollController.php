<?php

declare(strict_types=1);

namespace Plugin\Firetab\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use DateTime;
use PDOException;
use Plugin\Firetab\Models\FireStatusQueueEntry;

/**
 * FiveM Fire-Status-Polling-Endpoint.
 *
 * gepollt, um neue Status-Änderungen für Fire-Vehicles abzuholen. Jeder
 * abgeholte Datensatz wird sofort als `delivered=1` markiert (At-most-once
 * Delivery semantik — falls der FiveM-Server zwischendurch crasht, gehen
 * Status-Updates verloren, das ist akzeptiert weil sie eh transient sind).
 *
 * Auth: ApiKeyMiddleware (im Router registriert).
 */
final class FireStatusPollController
{
    public function poll(Request $request): Response
    {
        try {
            $pending = FireStatusQueueEntry::where('delivered', 0)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'vehicle_name', 'new_status', 'incident_number', 'created_at']);

            $statusChanges = [];
            $idsToMark     = [];

            foreach ($pending as $row) {
                $createdAt = new DateTime($row->created_at);
                $statusChanges[] = [
                    'vehicle_name'    => $row->vehicle_name,
                    'status'          => $row->new_status,
                    'incident_number' => $row->incident_number,
                    'timestamp'       => $createdAt->format('d.m.Y H:i'),
                ];
                $idsToMark[] = (int) $row->id;
            }

            if (!empty($idsToMark)) {
                FireStatusQueueEntry::whereIn('id', $idsToMark)->update(['delivered' => 1]);
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
