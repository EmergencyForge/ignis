<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

use App\Notifications\NotificationManager;

$notificationManager = new NotificationManager($pdo);
$userId = $_SESSION['userid'];
$since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

try {
    $result = $notificationManager->getNewSince($userId, $since);
    echo json_encode([
        'success' => true,
        'unreadCount' => $result['unreadCount'],
        'new' => $result['new']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
