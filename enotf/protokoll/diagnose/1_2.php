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
<html lang="en">

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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_2.php?enr=<?= $daten['enr'] ?>" class="active">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/diagnose/1_10.php?enr=<?= $daten['enr'] ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="diagnose_haupt-11" name="diagnose_haupt" value="11" <?php echo ($daten['diagnose_haupt'] == 11 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-11">ACS / NSTEMI</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-12" name="diagnose_haupt" value="12" <?php echo ($daten['diagnose_haupt'] == 12 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-12">ACS / STEMI</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-13" name="diagnose_haupt" value="13" <?php echo ($daten['diagnose_haupt'] == 13 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-13">Kardiogener Schock</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-14" name="diagnose_haupt" value="14" <?php echo ($daten['diagnose_haupt'] == 14 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-14">tachykarde Arrhythmie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-15" name="diagnose_haupt" value="15" <?php echo ($daten['diagnose_haupt'] == 15 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-15">bradykarde Arrhythmie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-16" name="diagnose_haupt" value="16" <?php echo ($daten['diagnose_haupt'] == 16 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-16">Schrittmacher-/ICD Fehlfunktion</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-17" name="diagnose_haupt" value="17" <?php echo ($daten['diagnose_haupt'] == 17 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-17">Lungenembolie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-18" name="diagnose_haupt" value="18" <?php echo ($daten['diagnose_haupt'] == 18 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-18">Lungenödem</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-19" name="diagnose_haupt" value="19" <?php echo ($daten['diagnose_haupt'] == 19 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-19">hypertensiver Notfall</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-20" name="diagnose_haupt" value="20" <?php echo ($daten['diagnose_haupt'] == 20 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-20">Aortenaneurysma</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="diagnose_haupt-21" name="diagnose_haupt" value="21" <?php echo ($daten['diagnose_haupt'] == 21 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-21">Hypotonie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-22" name="diagnose_haupt" value="22" <?php echo ($daten['diagnose_haupt'] == 22 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-22">Synkope</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-23" name="diagnose_haupt" value="23" <?php echo ($daten['diagnose_haupt'] == 23 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-23">Thrombose / art. Verschluss</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-24" name="diagnose_haupt" value="24" <?php echo ($daten['diagnose_haupt'] == 24 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-24">Herz-Kreislauf-Stillstand</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-25" name="diagnose_haupt" value="25" <?php echo ($daten['diagnose_haupt'] == 25 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-25">Schock unklarer Genese</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-26" name="diagnose_haupt" value="26" <?php echo ($daten['diagnose_haupt'] == 26 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-26">unklarer Thoraxschmerz</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-27" name="diagnose_haupt" value="27" <?php echo ($daten['diagnose_haupt'] == 27 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-27">orthostatische Fehlregulation</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-28" name="diagnose_haupt" value="28" <?php echo ($daten['diagnose_haupt'] == 28 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-28">hypertensive Krise / Entgleisung</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-29" name="diagnose_haupt" value="29" <?php echo ($daten['diagnose_haupt'] == 29 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-29">sonstige Erkrankung Herz-Kreislauf</label>
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