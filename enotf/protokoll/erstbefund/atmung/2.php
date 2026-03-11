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
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="erstbefund" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_symptome,b_auskult" class="active">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/kreislauf/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/index.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3">
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
                                id="atmung-ohne-path"
                                data-quickfill='{"b_symptome": 0, "b_auskult": 0}'
                                autocomplete="off">
                            <label for="atmung-ohne-path" class="edivi__unauffaellig">ohne path. Befund</label>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/1.php?enr=<?= $daten['enr'] ?>" data-requires="b_symptome">
                                <span>Beurteilung Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/2.php?enr=<?= $daten['enr'] ?>" data-requires="b_auskult" class="active">
                                <span>Auskultation</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="b_auskult-0" name="b_auskult" value="0" <?php echo ($daten['b_auskult'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-0" class="edivi__unauffaellig">unauffällig</label>

                            <input type="radio" class="btn-check" id="b_auskult-1" name="b_auskult" value="1" <?php echo ($daten['b_auskult'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-1">Spastik</label>

                            <input type="radio" class="btn-check" id="b_auskult-2" name="b_auskult" value="2" <?php echo ($daten['b_auskult'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-2">Stridor</label>

                            <input type="radio" class="btn-check" id="b_auskult-3" name="b_auskult" value="3" <?php echo ($daten['b_auskult'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-3">Rasselgeräusche</label>

                            <input type="radio" class="btn-check" id="b_auskult-4" name="b_auskult" value="4" <?php echo ($daten['b_auskult'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-4">Andere Pathologien</label>

                            <input type="radio" class="btn-check" id="b_auskult-98" name="b_auskult" value="98" <?php echo ($daten['b_auskult'] == 98 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-98">nicht beurteilbar</label>

                            <input type="radio" class="btn-check" id="b_auskult-99" name="b_auskult" value="99" <?php echo ($daten['b_auskult'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="b_auskult-99">nicht untersucht</label>
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