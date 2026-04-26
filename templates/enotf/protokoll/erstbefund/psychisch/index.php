<?php
/**
 * View: enotf/protokoll/erstbefund/psychisch/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;

use App\Helpers\EnotfUrl;
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

$psych = [];
if (!empty($daten['psych'])) {
    $decoded = json_decode($daten['psych'], true);
    if (is_array($decoded)) {
        $psych = array_map('intval', $decoded);
    }
}

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
    include __DIR__ . '/../../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="erstbefund" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 flex flex-col edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege') ?>" data-requires="awfrei_1,zyanose_1">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atmung') ?>" data-requires="b_symptome,b_auskult">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'kreislauf') ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie') ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3">
                                <span>Neurologie</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>">
                                <span>Erweitern</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'ekg') ?>" data-requires="c_ekg">
                                <span>EKG-Befund</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" data-requires="psych" class="active">
                                <span>psych. Zustand</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                <span>Messwerte</span>
                            </a>
                        </div>
                        <div class="col-2 flex flex-col edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="psych-1" name="psych[]" value="1" <?php echo (in_array(1, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-1" class="edivi__unauffaellig">unauffällig</label>

                            <input type="checkbox" class="btn-check" id="psych-2" name="psych[]" value="2" <?php echo (in_array(2, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-2">aggressiv</label>

                            <input type="checkbox" class="btn-check" id="psych-3" name="psych[]" value="3" <?php echo (in_array(3, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-3">depressiv</label>

                            <input type="checkbox" class="btn-check" id="psych-4" name="psych[]" value="4" <?php echo (in_array(4, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-4">wahnhaft</label>

                            <input type="checkbox" class="btn-check" id="psych-5" name="psych[]" value="5" <?php echo (in_array(5, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-5">verwirrt</label>

                            <input type="checkbox" class="btn-check" id="psych-6" name="psych[]" value="6" <?php echo (in_array(6, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-6">verlangsamt</label>

                            <input type="checkbox" class="btn-check" id="psych-7" name="psych[]" value="7" <?php echo (in_array(7, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-7">euphorisch</label>

                            <input type="checkbox" class="btn-check" id="psych-8" name="psych[]" value="8" <?php echo (in_array(8, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-8">erregt</label>

                            <input type="checkbox" class="btn-check" id="psych-9" name="psych[]" value="9" <?php echo (in_array(9, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-9">ängstlich</label>
                        </div>
                        <div class="col-2 flex flex-col edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="psych-10" name="psych[]" value="10" <?php echo (in_array(10, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-10">suizidal</label>

                            <input type="checkbox" class="btn-check" id="psych-11" name="psych[]" value="11" <?php echo (in_array(11, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-11">motorisch unruhig</label>

                            <input type="checkbox" class="btn-check" id="psych-12" name="psych[]" value="12" <?php echo (in_array(12, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-12">Sonstige</label>

                            <input type="checkbox" class="btn-check" id="psych-98" name="psych[]" value="98" <?php echo (in_array(98, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-98">nicht beurteilbar</label>

                            <input type="checkbox" class="btn-check" id="psych-99" name="psych[]" value="99" <?php echo (in_array(99, $psych) ? 'checked' : '') ?> autocomplete="off">
                            <label for="psych-99">nicht untersucht</label>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../../assets/functions/enotf/clock.php';
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