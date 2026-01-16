<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
    exit();
}

use App\Auth\Permissions;
use App\Utils\AuditLogger;

// Check if user has admin permissions
if (!Permissions::check(['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit();
}

try {
    // Generate new API key (32 bytes = 64 hexadecimal characters)
    $newApiKey = bin2hex(random_bytes(32));
    
    // Update API key in database
    $stmt = $pdo->prepare("
        UPDATE intra_config 
        SET config_value = ?, updated_by = ?, updated_at = NOW()
        WHERE config_key = 'API_KEY'
    ");
    
    $success = $stmt->execute([$newApiKey, $_SESSION['userid']]);
    
    // Check if any rows were affected
    if ($success && $stmt->rowCount() > 0) {
        // Log the action in audit log
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            'API-Schlüssel neu generiert',
            'Neuer API-Schlüssel wurde erstellt',
            'System',
            1  // Config updates are global
        );
        
        echo json_encode([
            'success' => true,
            'api_key' => $newApiKey,
            'message' => 'API-Schlüssel erfolgreich generiert'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'API_KEY wurde nicht in der Datenbank gefunden oder konnte nicht aktualisiert werden'
        ]);
    }
} catch (Exception $e) {
    error_log("Error regenerating API key: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Interner Serverfehler'
    ]);
}
