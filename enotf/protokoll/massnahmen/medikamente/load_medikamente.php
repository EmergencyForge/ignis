<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../../../assets/config/config.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['enr'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ENR fehlt']);
    exit();
}

$enr = $_POST['enr'];

try {
    $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['enr' => $enr]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode([]);
        exit();
    }

    $medikamente = [];
    if (!empty($result['medis']) && $result['medis'] !== '0') {
        $decoded = json_decode($result['medis'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $medikamente = $decoded;
        }
    }

    // Medikamente nach Zeit sortieren (neueste zuerst)
    usort($medikamente, function ($a, $b) {
        return strcmp($b['zeit'], $a['zeit']);
    });

    echo json_encode($medikamente, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
    error_log("Database error in load_medikamente.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unerwarteter Fehler']);
    error_log("Error in load_medikamente.php: " . $e->getMessage());
}
