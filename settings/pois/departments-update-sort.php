<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (!Permissions::check(['admin', 'pois.manage'])) {
    echo json_encode(['success' => false, 'error' => 'No permissions']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['department_id']) || !isset($input['sort_order'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$department_id = (int)$input['department_id'];
$sort_order = (int)$input['sort_order'];

try {
    $stmt = $pdo->prepare("UPDATE intra_edivi_hospital_departments SET sort_order = :sort_order WHERE id = :id");
    $stmt->execute([
        'sort_order' => $sort_order,
        'id' => $department_id
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
