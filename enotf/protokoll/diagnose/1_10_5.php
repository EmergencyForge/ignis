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
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1.php?enr=<?= $daten['enr'] ?>" data-requires="diagnose_haupt" class="active">
                                <span>Diagnose (führend)</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/2.php?enr=<?= $daten['enr'] ?>">
                                <span>Diagnose (weitere)</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/3.php?enr=<?= $daten['enr'] ?>">
                                <span>Diagnose Text</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_1.php?enr=<?= $daten['enr'] ?>">
                                <span>ZNS</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_2.php?enr=<?= $daten['enr'] ?>">
                                <span>Herz-Kreislauf</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_3.php?enr=<?= $daten['enr'] ?>">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_4.php?enr=<?= $daten['enr'] ?>">
                                <span>Abdomen</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_5.php?enr=<?= $daten['enr'] ?>">
                                <span>Psychiatrie</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_6.php?enr=<?= $daten['enr'] ?>">
                                <span>Stoffwechsel</span>
                            </a>


                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_9.php?enr=<?= $daten['enr'] ?>">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_1.php?enr=<?= $daten['enr'] ?>">
                                <span>Schädel-Hirn</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_2.php?enr=<?= $daten['enr'] ?>">
                                <span>Gesicht</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_3.php?enr=<?= $daten['enr'] ?>">
                                <span>HWS</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_4.php?enr=<?= $daten['enr'] ?>">
                                <span>Thorax</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_5.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Abdomen</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_6.php?enr=<?= $daten['enr'] ?>">
                                <span>BWS / LWS</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_7.php?enr=<?= $daten['enr'] ?>">
                                <span>Becken</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_8.php?enr=<?= $daten['enr'] ?>">
                                <span>obere Extremitäten</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_9.php?enr=<?= $daten['enr'] ?>">
                                <span>untere Extremitäten</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_10.php?enr=<?= $daten['enr'] ?>">
                                <span>Weichteile</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10_11.php?enr=<?= $daten['enr'] ?>">
                                <span>spezielle</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="diagnose_haupt-141" name="diagnose_haupt" value="141" <?php echo ($daten['diagnose_haupt'] == 141 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-141">leicht</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-142" name="diagnose_haupt" value="142" <?php echo ($daten['diagnose_haupt'] == 142 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-142">mittel</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-143" name="diagnose_haupt" value="143" <?php echo ($daten['diagnose_haupt'] == 143 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-143">schwer</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-144" name="diagnose_haupt" value="144" <?php echo ($daten['diagnose_haupt'] == 144 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-144">tödlich</label>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../assets/functions/enotf/clock.php';
    ?>
    <?php if ($ist_freigegeben) : ?>
        <script>
            var formElements = document.querySelectorAll('input, textarea');
            var selectElements2 = document.querySelectorAll('select');
            var inputElements2 = document.querySelectorAll('.btn-check');
            var inputElements3 = document.querySelectorAll('.form-check-input');

            formElements.forEach(function(element) {
                element.setAttribute('readonly', 'readonly');
            });

            selectElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements3.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });
        </script>
    <?php endif; ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>