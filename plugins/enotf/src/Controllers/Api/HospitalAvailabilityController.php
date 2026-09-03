<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;
use Plugin\Enotf\Models\HospitalDepartment;

/**
 * Hospital-Availability-Endpoints (GET listing, POST update).
 *
 * Liefert bzw. aktualisiert Verfügbarkeits-Status für Krankenhaus-
 * Abteilungen (intra_edivi_hospital_availability). Wird von der
 * eNOTF-Klinik-Übersicht sowie dem Admin-Panel genutzt.
 */
final class HospitalAvailabilityController
{
    private const VALID_STATUSES = ['not_staffed', 'available', 'partially_available', 'full'];

    /**
     * GET /api/hospitals/availability-get[?poi_id=N]
     */
    public function get(Request $request): Response
    {
        $poiId = $request->query['poi_id'] ?? null;

        try {
            $query = DB::table('intra_edivi_pois as p')
                ->leftJoin('intra_edivi_hospital_departments as d', 'p.id', '=', 'd.poi_id')
                ->leftJoin('intra_edivi_hospital_availability as a', 'd.id', '=', 'a.department_id')
                ->select(
                    'p.id as poi_id',
                    'p.name as hospital_name',
                    'p.ort as city',
                    'p.ortsteil as district',
                    'p.typ as type',
                    'd.id as department_id',
                    'd.name as department_name',
                    'd.sort_order',
                    DB::raw("COALESCE(a.status, 'not_staffed') as status"),
                    'a.updated_at',
                    'a.updated_by'
                )
                ->where('p.active', 1)
                ->where(function ($q) {
                    $q->where('p.typ', 'Krankenhaus')->orWhere('p.typ', 'Klinik');
                });

            if ($poiId) {
                $query->where('p.id', $poiId);
            }

            $results = $query
                ->orderBy('p.name')
                ->orderBy('d.sort_order')
                ->orderBy('d.name')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            // Nach Hospital gruppieren
            $hospitals = [];
            foreach ($results as $row) {
                $pid = $row['poi_id'];
                if (!isset($hospitals[$pid])) {
                    $hospitals[$pid] = [
                        'poi_id'        => $pid,
                        'hospital_name' => $row['hospital_name'],
                        'city'          => $row['city'],
                        'district'      => $row['district'],
                        'type'          => $row['type'],
                        'departments'   => [],
                    ];
                }
                if ($row['department_id']) {
                    $hospitals[$pid]['departments'][] = [
                        'department_id'   => $row['department_id'],
                        'department_name' => $row['department_name'],
                        'status'          => $row['status'],
                        'updated_at'      => $row['updated_at'],
                        'updated_by'      => $row['updated_by'],
                    ];
                }
            }

            return Response::json([
                'success' => true,
                'data'    => array_values($hospitals),
            ]);
        } catch (PDOException $e) {
            Logger::error('HospitalAvailability: GET Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    /**
     * POST /api/hospitals/availability-update
     *
     * Body: { "department_id": N, "status": "available|partially_available|full|not_staffed", "updated_by": "..." }
     */
    public function update(Request $request): Response
    {
        $input = $request->json();
        if (!is_array($input)) {
            return Response::json(['error' => 'Missing required fields: department_id, status'], 400);
        }

        $departmentId = $input['department_id'] ?? null;
        $status       = $input['status']        ?? null;
        $updatedBy    = $input['updated_by']    ?? 'System';

        if (!$departmentId || !$status) {
            return Response::json(['error' => 'Missing required fields: department_id, status'], 400);
        }

        if (!in_array($status, self::VALID_STATUSES, true)) {
            return Response::json([
                'error' => 'Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES),
            ], 400);
        }

        try {
            if (!HospitalDepartment::where('id', $departmentId)->exists()) {
                return Response::json(['error' => 'Department not found'], 404);
            }

            // Upsert: unique key auf department_id. updated_at wird auch bei
            // unverändertem Status aufgefrischt (wie das alte ON DUPLICATE
            // KEY UPDATE mit explizitem CURRENT_TIMESTAMP).
            DB::table('intra_edivi_hospital_availability')->upsert(
                [
                    'department_id' => $departmentId,
                    'status'        => $status,
                    'updated_by'    => $updatedBy,
                ],
                ['department_id'],
                ['status', 'updated_by', 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]
            );

            return Response::json([
                'success' => true,
                'message' => 'Availability updated successfully',
                'data'    => [
                    'department_id' => $departmentId,
                    'status'        => $status,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (PDOException $e) {
            Logger::error('HospitalAvailability: UPDATE Fehler', [
                'error'         => $e->getMessage(),
                'department_id' => $departmentId,
            ]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }
}
