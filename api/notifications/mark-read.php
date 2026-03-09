<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

use App\Notifications\NotificationManager;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$notificationManager = new NotificationManager($pdo);
$userId = $_SESSION['userid'];

// Get the notification ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['id'] ?? null;

if (!$notificationId || !is_numeric($notificationId)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid notification ID']));
}

try {
    $result = $notificationManager->markAsRead((int)$notificationId, $userId);
    
    if ($result) {
        exit(json_encode(['success' => true, 'message' => 'Notification marked as read']));
    } else {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Notification not found']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Server error']));
}
