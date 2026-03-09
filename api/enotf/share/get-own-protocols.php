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
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['protfzg'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$current_vehicle = $_SESSION['protfzg'];

try {
    // Lade eigene nicht-freigegebene Protokolle
    // Prüfe ob hidden_user Spalte existiert
    $checkColumn = $pdo->query("SHOW COLUMNS FROM intra_edivi LIKE 'hidden_user'");
    $hasHiddenUser = $checkColumn->rowCount() > 0;

    $hiddenUserCondition = $hasHiddenUser ? 'AND (hidden_user = 0 OR hidden_user IS NULL)' : '';

    // Baue SQL-Query dynamisch auf
    $sql = "
        SELECT 
            id,
            enr,
            patname,
            edatum,
            ezeit,
            prot_by
        FROM intra_edivi 
        WHERE (fzg_transp = :vehicle1 OR fzg_na = :vehicle2)
        AND freigegeben = 0 
        AND (hidden = 0 OR hidden IS NULL)
        " . $hiddenUserCondition . "
        ORDER BY edatum DESC, ezeit DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':vehicle1' => $current_vehicle,
        ':vehicle2' => $current_vehicle
    ]);

    $protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'protocols' => $protocols,
        'count' => count($protocols),
        'debug' => [
            'vehicle' => $current_vehicle,
            'hasHiddenUser' => $hasHiddenUser,
            'sql' => $sql
        ]
    ]);
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Protokolle: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}
