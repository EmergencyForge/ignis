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
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$mode = $_GET['mode'] ?? 'all';
$vehicle = $_SESSION['protfzg'] ?? null;
$position = $_SESSION['enotf_position'] ?? null;
$sessionToken = $_SESSION['enotf_session_token'] ?? null;

if ($mode === 'self' && $vehicle && $position && $sessionToken) {
    // Einzeln abmelden: Eigene Position in der Fahrzeug-Session leeren
    $posNameCol = $position . 'name';
    $posQualiCol = $position . 'quali';

    // Session-ID über Token finden
    $stmt = $pdo->prepare("
        SELECT m.session_id FROM intra_enotf_session_members m
        JOIN intra_enotf_sessions s ON s.id = m.session_id
        WHERE m.session_token = :token AND s.active = 1
        LIMIT 1
    ");
    $stmt->execute([':token' => $sessionToken]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        $sessionId = $member['session_id'];

        // Position in der Session leeren
        $updateStmt = $pdo->prepare("UPDATE intra_enotf_sessions SET $posNameCol = NULL, $posQualiCol = NULL WHERE id = :id");
        $updateStmt->execute([':id' => $sessionId]);

        // Eigenen Member-Eintrag löschen
        $deleteStmt = $pdo->prepare("DELETE FROM intra_enotf_session_members WHERE session_token = :token");
        $deleteStmt->execute([':token' => $sessionToken]);

        // Prüfen ob noch Positionen besetzt sind
        $checkStmt = $pdo->prepare("SELECT fahrername, beifahrername, praktikantname FROM intra_enotf_sessions WHERE id = :id");
        $checkStmt->execute([':id' => $sessionId]);
        $remaining = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (empty($remaining['fahrername']) && empty($remaining['beifahrername']) && empty($remaining['praktikantname'])) {
            // Letzte Person hat sich abgemeldet → Session deaktivieren
            $pdo->prepare("UPDATE intra_enotf_sessions SET active = 0 WHERE id = :id")->execute([':id' => $sessionId]);
        }
    }
} else {
    // Alle abmelden: Gesamte Fahrzeug-Session deaktivieren
    if ($vehicle) {
        $pdo->prepare("UPDATE intra_enotf_sessions SET active = 0 WHERE vehicle_identifier = :vehicle AND active = 1")
            ->execute([':vehicle' => $vehicle]);
    }
}

// PHP-Session-Variablen löschen
unset(
    $_SESSION['fahrername'],
    $_SESSION['fahrerquali'],
    $_SESSION['beifahrername'],
    $_SESSION['beifahrerquali'],
    $_SESSION['praktikantname'],
    $_SESSION['praktikantquali'],
    $_SESSION['protfzg'],
    $_SESSION['enotf_session_token'],
    $_SESSION['enotf_position']
);

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="edivi__login-buttons">
                        <div class="row">
                            <div class="col">
                                Sie sind nicht angemeldet!
                            </div>
                            <div class="col-3">
                                <a href="login.php">anmelden</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
