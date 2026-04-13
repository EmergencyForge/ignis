<?php
/**
 * Hospital Availability Get API
 * Retrieves current availability status for all hospitals or a specific hospital
 */

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

header('Content-Type: application/json');

// Get query parameters
$poi_id = $_GET['poi_id'] ?? null;

try {
    $sql = "
        SELECT
            p.id as poi_id,
            p.name as hospital_name,
            p.ort as city,
            p.ortsteil as district,
            p.typ as type,
            d.id as department_id,
            d.name as department_name,
            d.sort_order,
            COALESCE(a.status, 'not_staffed') as status,
            a.updated_at,
            a.updated_by
        FROM intra_edivi_pois p
        LEFT JOIN intra_edivi_hospital_departments d ON p.id = d.poi_id
        LEFT JOIN intra_edivi_hospital_availability a ON d.id = a.department_id
        WHERE p.active = 1
        AND (p.typ = 'Krankenhaus' OR p.typ = 'Klinik')
    ";

    if ($poi_id) {
        $sql .= " AND p.id = :poi_id";
    }

    $sql .= " ORDER BY p.name ASC, d.sort_order ASC, d.name ASC";

    $stmt = $pdo->prepare($sql);
    if ($poi_id) {
        $stmt->execute(['poi_id' => $poi_id]);
    } else {
        $stmt->execute();
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by hospital
    $hospitals = [];
    foreach ($results as $row) {
        $poi_id = $row['poi_id'];

        if (!isset($hospitals[$poi_id])) {
            $hospitals[$poi_id] = [
                'poi_id' => $poi_id,
                'hospital_name' => $row['hospital_name'],
                'city' => $row['city'],
                'district' => $row['district'],
                'type' => $row['type'],
                'departments' => []
            ];
        }

        if ($row['department_id']) {
            $hospitals[$poi_id]['departments'][] = [
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'status' => $row['status'],
                'updated_at' => $row['updated_at'],
                'updated_by' => $row['updated_by']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($hospitals)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
