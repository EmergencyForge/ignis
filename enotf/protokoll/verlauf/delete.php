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
require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit();
}

// ID prüfen
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo 'Ungültige ID';
    exit();
}

$id = intval($_POST['id']);

try {
    // Prüfen ob der Eintrag existiert und zu einem nicht freigegebenen Fall gehört
    $checkQuery = "SELECT v.id, e.freigegeben 
                   FROM intra_edivi_vitalparameter v 
                   JOIN intra_edivi e ON v.enr = e.enr 
                   WHERE v.id = :id";

    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute(['id' => $id]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo 'Eintrag nicht gefunden';
        exit();
    }

    if ($result['freigegeben'] == 1) {
        echo 'Eintrag kann nicht gelöscht werden - Fall ist bereits freigegeben';
        exit();
    }

    // Löschen
    $deleteQuery = "DELETE FROM intra_edivi_vitalparameter WHERE id = :id";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $success = $deleteStmt->execute(['id' => $id]);

    if ($success && $deleteStmt->rowCount() > 0) {
        echo 'success';
    } else {
        echo 'Fehler beim Löschen';
    }
} catch (PDOException $e) {
    error_log('Database error in delete.php: ' . $e->getMessage());
    echo 'Datenbankfehler';
} catch (Exception $e) {
    error_log('General error in delete.php: ' . $e->getMessage());
    echo 'Unbekannter Fehler';
}
