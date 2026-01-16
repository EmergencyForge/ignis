<?php
/**
 * Search suggestions API for Knowledge Base
 * Returns article suggestions based on search query
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

use App\KnowledgeBase\KBHelper;

// Check if public access is enabled or user is logged in
$publicAccess = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['permissions']);

if (!$publicAccess && !$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get search query
$query = $_GET['q'] ?? '';

// Require at least 2 characters
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

try {
    $searchParam = '%' . $query . '%';
    
    $sql = "SELECT id, type, title, subtitle, competency_level 
            FROM intra_kb_entries 
            WHERE is_archived = 0 
            AND (title LIKE :search1 OR subtitle LIKE :search2 OR med_wirkstoff LIKE :search3)
            ORDER BY is_pinned DESC, title ASC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'search1' => $searchParam,
        'search2' => $searchParam,
        'search3' => $searchParam
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add type labels and colors
    foreach ($results as &$result) {
        $result['type_label'] = KBHelper::getTypeLabel($result['type']);
        $result['type_color'] = KBHelper::getTypeColor($result['type']);
        if ($result['competency_level']) {
            $competency = KBHelper::getCompetencyInfo($result['competency_level']);
            $result['competency_label'] = $competency['label'];
            $result['competency_color'] = $competency['color'];
        }
    }
    
    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
