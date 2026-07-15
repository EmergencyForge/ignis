<?php
/**
 * View: enotf/protokoll/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;
use Plugin\Enotf\Helpers\EnotfUrl;

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);

    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$daten) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }

    // Prüfe ob Klinikzugriff und ob auf das richtige Protokoll zugegriffen wird
    if (isset($_SESSION['klinik_access_enr'])) {
        if ($_SESSION['klinik_access_enr'] !== $_GET['enr']) {
            // Zugriff auf anderes Protokoll als freigegeben - nicht erlaubt
            header("Location: " . EnotfUrl::schnittstelle('klinikcode'));
            exit();
        }

        // Prüfe ob das Protokoll noch freigegeben ist
        if ($daten['freigegeben'] != 1) {
            \App\Session\SessionManager::clearKlinikAccess();
            header("Location: " . EnotfUrl::schnittstelle('klinikcode'));
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

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');

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
    include dirname(__DIR__, 5) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include dirname(__DIR__, 5) . '/assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include dirname(__DIR__, 5) . '/assets/components/enotf/nav.php'; ?>
            </div>
        </div>
    </form>
    <?php
    include dirname(__DIR__, 5) . '/assets/functions/enotf/clock.php';
    include dirname(__DIR__, 5) . '/assets/functions/enotf/notify.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>