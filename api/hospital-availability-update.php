<?php
/**
 * Hospital Availability Update API
 * Updates availability status for hospital departments
 */

require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$department_id = $input['department_id'] ?? null;
$status = $input['status'] ?? null;
$updated_by = $input['updated_by'] ?? 'System';

// Validate input
if (!$department_id || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: department_id, status']);
    exit();
}

// Validate status
$valid_statuses = ['not_staffed', 'available', 'partially_available', 'full'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status. Must be one of: ' . implode(', ', $valid_statuses)]);
    exit();
}

try {
    // Check if department exists
    $stmt = $pdo->prepare("SELECT id FROM intra_edivi_hospital_departments WHERE id = ?");
    $stmt->execute([$department_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Department not found']);
        exit();
    }

    // Update or insert availability
    $stmt = $pdo->prepare("
        INSERT INTO intra_edivi_hospital_availability (department_id, status, updated_by)
        VALUES (:department_id, :status, :updated_by)
        ON DUPLICATE KEY UPDATE
            status = :status,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        'department_id' => $department_id,
        'status' => $status,
        'updated_by' => $updated_by
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Availability updated successfully',
        'data' => [
            'department_id' => $department_id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
