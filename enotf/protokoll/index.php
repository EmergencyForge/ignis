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
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;

// ──── Feature Flag: Modulares Protokollsystem ────
if (defined('ENOTF_MODULAR_FORMS') && ENOTF_MODULAR_FORMS === true && isset($_GET['enr'])) {
    // Redirect zur ersten dynamischen Sektion
    header("Location: " . BASE_PATH . "enotf/protokoll/" . urlencode($_GET['enr']) . "/rettdaten");
    exit();
}

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);

    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    if (count($daten) == 0) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }

    // Prüfe ob Klinikzugriff und ob auf das richtige Protokoll zugegriffen wird
    if (isset($_SESSION['klinik_access_enr'])) {
        if ($_SESSION['klinik_access_enr'] !== $_GET['enr']) {
            // Zugriff auf anderes Protokoll als freigegeben - nicht erlaubt
            header("Location: " . BASE_PATH . "enotf/schnittstelle/klinikcode.php");
            exit();
        }

        // Prüfe ob das Protokoll noch freigegeben ist
        if ($daten['freigegeben'] != 1) {
            unset($_SESSION['klinik_access_enr']);
            unset($_SESSION['klinik_access_time']);
            header("Location: " . BASE_PATH . "enotf/schnittstelle/klinikcode.php");
            exit();
        }
    }
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

if ($daten['freigegeben'] == 1) {
    $ist_freigegeben = true;
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../assets/components/enotf/nav.php'; ?>
            </div>
        </div>
    </form>
    <?php
    include __DIR__ . '/../../assets/functions/enotf/clock.php';
    include __DIR__ . '/../../assets/functions/enotf/notify.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>