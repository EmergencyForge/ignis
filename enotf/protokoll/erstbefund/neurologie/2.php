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

<body data-bs-theme="dark" data-page="erstbefund" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awfrei_1,zyanose_1">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_symptome,b_auskult">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/kreislauf/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/index.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3" class="active">
                                <span>Neurologie</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/index.php?enr=<?= $daten['enr'] ?>">
                                <span>Erweitern</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/ekg/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_ekg">
                                <span>EKG-Befund</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/psychisch/index.php?enr=<?= $daten['enr'] ?>" data-requires="psych">
                                <span>psych. Zustand</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/messwerte/index.php?enr=<?= $daten['enr'] ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                <span>Messwerte</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <input type="checkbox"
                                class="btn-check"
                                id="neuro-ohne-path"
                                data-quickfill='{"d_bewusstsein": 1, "d_ex_1": 1, "d_pupillenw_1": 2, "d_pupillenw_2": 2, "d_lichtreakt_1": 1, "d_lichtreakt_2": 1, "d_gcs_1": 0, "d_gcs_2": 0, "d_gcs_3": 0}'
                                autocomplete="off">
                            <label for="neuro-ohne-path" class="edivi__unauffaellig">ohne path. Befund</label>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/1.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein">
                                <span>Bewusstseinslage</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/2.php?enr=<?= $daten['enr'] ?>" data-requires="d_ex_1" class="active">
                                <span>Extremitätenbewegung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/3.php?enr=<?= $daten['enr'] ?>" data-requires="d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2">
                                <span>Pupillen</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/4.php?enr=<?= $daten['enr'] ?>" data-requires="d_gcs_1,d_gcs_2,d_gcs_3">
                                <span>GCS</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="d_ex_1-1" name="d_ex_1" value="1" <?php echo ($daten['d_ex_1'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_ex_1-1" class="edivi__unauffaellig">uneingeschränkt</label>

                            <input type="radio" class="btn-check" id="d_ex_1-2" name="d_ex_1" value="2" <?php echo ($daten['d_ex_1'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_ex_1-2">leicht eingeschränkt</label>

                            <input type="radio" class="btn-check" id="d_ex_1-3" name="d_ex_1" value="3" <?php echo ($daten['d_ex_1'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_ex_1-3">stark eingeschränkt</label>

                            <input type="radio" class="btn-check" id="d_ex_1-99" name="d_ex_1" value="99" <?php echo ($daten['d_ex_1'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_ex_1-99">Nicht beurteilbar</label>
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