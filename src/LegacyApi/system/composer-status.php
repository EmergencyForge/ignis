<?php
/**
 * Composer Status API Endpoint
 * 
 * Handles composer installation status and execution after system updates
 * 
 * Usage: 
 * - GET /api/composer-status.php?action=check - Check if composer install is pending
 * - POST /api/composer-status.php?action=execute - Execute pending composer install
 * 
 * Response format:
 * {
 *   "success": true|false,
 *   "pending": true|false,
 *   "message": "Status message",
 *   "executed": true|false,
 *   "output": "Composer output"
 * }
 */
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth\Permissions;
use App\Utils\SystemUpdater;

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Nicht authentifiziert.'
    ]);
    exit;
}

if (!Permissions::check(['admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Keine Berechtigung.'
    ]);
    exit;
}

// Determine action based on request method for security
$action = $_GET['action'] ?? 'check';

// For POST requests, action must come from POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'execute';
}

$updater = new SystemUpdater();

try {
    switch ($action) {
        case 'check':
            // Check if composer install is pending (GET only)
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'Methode nicht erlaubt. Verwenden Sie GET für "check".'
                ]);
                break;
            }
            
            $status = $updater->getComposerStatus();
            echo json_encode($status);
            break;
            
        case 'execute':
            // Execute composer install (POST only)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'Methode nicht erlaubt. Verwenden Sie POST für "execute".'
                ]);
                break;
            }
            
            $result = $updater->executePendingComposerInstall();
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => 'Ungültige Aktion. Verwenden Sie "check" oder "execute".'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Fehler: ' . $e->getMessage()
    ]);
}
