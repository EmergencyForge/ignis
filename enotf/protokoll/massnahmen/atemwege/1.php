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
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

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
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu" class="active">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_zugang">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/weitere/index.php?enr=<?= $daten['enr'] ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/1.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu" class="active">
                                <span>Atemwegssicherung</span>
                            </a>

                            <input type="checkbox" class="btn-check" id="awsicherung_1-1" name="awsicherung_1" value="1" <?php echo ($daten['awsicherung_1'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_1-1">Atemwege freim.</label>

                            <input type="checkbox" class="btn-check" id="awsicherung_2-1" name="awsicherung_2" value="1" <?php echo ($daten['awsicherung_2'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_2-1">Absaugen</label>

                            <input type="checkbox" class="btn-check" id="entlastungspunktion-1" name="entlastungspunktion" value="1" <?php echo ($daten['entlastungspunktion'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="entlastungspunktion-1">Entlastungspunktion</label>

                            <input type="checkbox" class="btn-check" id="hws_immo-1" name="hws_immo" value="1" <?php echo ($daten['hws_immo'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="hws_immo-1">HWS-Immobilisation</label>
                        </div>
                        <div class="col-2 d-flex flex-column -edivi__interactbutton">
                            <input type="radio" class="btn-check" id="awsicherung_neu-1" name="awsicherung_neu" value="1" <?php echo ($daten['awsicherung_neu'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-1" class="edivi__unauffaellig">keine</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-2" name="awsicherung_neu" value="2" <?php echo ($daten['awsicherung_neu'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-2">Endotrachealtubus</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-6" name="awsicherung_neu" value="6" <?php echo ($daten['awsicherung_neu'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-6">Wendl-Tubus</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-3" name="awsicherung_neu" value="3" <?php echo ($daten['awsicherung_neu'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-3">Larynxmaske</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-5" name="awsicherung_neu" value="5" <?php echo ($daten['awsicherung_neu'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-5">Larynxtubus</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-4" name="awsicherung_neu" value="4" <?php echo ($daten['awsicherung_neu'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-4">Guedeltubus</label>

                            <input type="radio" class="btn-check" id="awsicherung_neu-99" name="awsicherung_neu" value="99" <?php echo ($daten['awsicherung_neu'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_neu-99">Sonstige</label>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
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