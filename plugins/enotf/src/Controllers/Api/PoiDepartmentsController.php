<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Http\Request;
use Plugin\Enotf\Requests\PoiDepartmentsSortRequest;
use App\Http\Response;
use App\Logging\Logger;
use PDO;
use PDOException;

/**
 * POI-Departments-Admin-Endpoints.
 *
 * Aktuell: Sort-Order-Update für `intra_edivi_hospital_departments`.
 * Wird vom Admin-Panel gerufen, wenn ein User die Reihenfolge der
 * Departments per Drag-and-Drop ändert.
 */
final class PoiDepartmentsController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/pois/departments-sort
     *
     * Body: { "department_id": int, "sort_order": int }
     */
    public function updateSort(Request $request): Response
    {
        $data = PoiDepartmentsSortRequest::validate($request);

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE intra_edivi_hospital_departments SET sort_order = :sort_order WHERE id = :id"
            );
            $stmt->execute([
                ':sort_order' => (int) $data['sort_order'],
                ':id'         => (int) $data['department_id'],
            ]);

            return Response::json(['success' => true]);
        } catch (PDOException $e) {
            Logger::error('PoiDepartments: Sort-Update-Fehler', [
                'error'         => $e->getMessage(),
                'department_id' => $data['department_id'],
            ]);
            return Response::json([
                'success' => false,
                'error'   => 'Datenbankfehler',
            ], 500);
        }
    }
}
