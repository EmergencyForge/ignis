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

<body data-bs-theme="dark" data-page="anamnese" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                                <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/1.php?enr=<?= $daten['enr'] ?>">
                                    <span>Anamnese</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2.php?enr=<?= $daten['enr'] ?>" data-requires="naca_initial">
                                    <span>Symptome</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/3.php?enr=<?= $daten['enr'] ?>" data-requires="elokation" class="active">
                                    <span>Einsatzort</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="elokation-1" name="elokation" value="1" <?= ($daten['elokation'] ?? '') == 1 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-1">Wohnung</label>

                            <input type="radio" class="btn-check" id="elokation-2" name="elokation" value="2" <?= ($daten['elokation'] ?? '') == 2 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-2">Arbeitsplatz</label>

                            <input type="radio" class="btn-check" id="elokation-3" name="elokation" value="3" <?= ($daten['elokation'] ?? '') == 3 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-3">Altenheim</label>

                            <input type="radio" class="btn-check" id="elokation-4" name="elokation" value="4" <?= ($daten['elokation'] ?? '') == 4 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-4">öffentlicher Raum</label>

                            <input type="radio" class="btn-check" id="elokation-5" name="elokation" value="5" <?= ($daten['elokation'] ?? '') == 5 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-5">Arztpraxis</label>

                            <input type="radio" class="btn-check" id="elokation-6" name="elokation" value="6" <?= ($daten['elokation'] ?? '') == 6 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-6">Straße</label>

                            <input type="radio" class="btn-check" id="elokation-7" name="elokation" value="7" <?= ($daten['elokation'] ?? '') == 7 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-7">Krankenhaus</label>

                            <input type="radio" class="btn-check" id="elokation-8" name="elokation" value="8" <?= ($daten['elokation'] ?? '') == 8 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-8">Massenveranstaltung</label>

                            <input type="radio" class="btn-check" id="elokation-9" name="elokation" value="9" <?= ($daten['elokation'] ?? '') == 9 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-9">Bildungseinrichtung</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="elokation-10" name="elokation" value="10" <?= ($daten['elokation'] ?? '') == 10 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-10">Sportstätte</label>

                            <input type="radio" class="btn-check" id="elokation-11" name="elokation" value="11" <?= ($daten['elokation'] ?? '') == 11 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-11">Geburtshaus/-einrichtung</label>

                            <input type="radio" class="btn-check" id="elokation-98" name="elokation" value="98" <?= ($daten['elokation'] ?? '') == 98 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-98">Sonstige</label>

                            <input type="radio" class="btn-check" id="elokation-99" name="elokation" value="99" <?= ($daten['elokation'] ?? '') == 99 ? 'checked' : '' ?> autocomplete="off">
                            <label for="elokation-99">nicht dokumentiert</label>
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