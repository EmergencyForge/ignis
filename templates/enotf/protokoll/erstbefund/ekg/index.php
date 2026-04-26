<?php
/**
 * View: enotf/protokoll/erstbefund/ekg/index.php
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
            <div class="flex flex-wrap -mx-3 h-full">
                <?php include __DIR__ . '/../../../../../assets/components/enotf/nav.php'; ?>
                <div class="flex-1 px-3" id="edivi__content" style="padding-left: 0">
                    <div class="flex flex-wrap -mx-3" style="margin-left: 0">
                        <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'ekg') ?>" data-requires="c_ekg" class="active">
                                <span>EKG-Befund</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" data-requires="psych">
                                <span>psych. Zustand</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                <span>Messwerte</span>
                            </a>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="c_ekg-1" name="c_ekg" value="1" <?php echo ($daten['c_ekg'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-1" class="edivi__unauffaellig">Sinusrhythmus</label>

                            <input type="radio" class="btn-check" id="c_ekg-3" name="c_ekg" value="3" <?php echo ($daten['c_ekg'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-3">Absolute Arrhythmie</label>

                            <input type="radio" class="btn-check" id="c_ekg-5" name="c_ekg" value="5" <?php echo ($daten['c_ekg'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-5">AV-Block II</label>

                            <input type="radio" class="btn-check" id="c_ekg-51" name="c_ekg" value="51" <?php echo ($daten['c_ekg'] == 51 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-51">AV-Block III</label>

                            <input type="radio" class="btn-check" id="c_ekg-4" name="c_ekg" value="4" <?php echo ($daten['c_ekg'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-4">Kammerflimmern,-flattern</label>

                            <input type="radio" class="btn-check" id="c_ekg-41" name="c_ekg" value="41" <?php echo ($daten['c_ekg'] == 41 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-41">Pulslose elektrische Aktivität</label>

                            <input type="radio" class="btn-check" id="c_ekg-6" name="c_ekg" value="6" <?php echo ($daten['c_ekg'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-6">Asystolie</label>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="c_ekg-21" name="c_ekg" value="21" <?php echo ($daten['c_ekg'] == 21 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-21">Schrittmacherrhythmus</label>

                            <input type="radio" class="btn-check" id="c_ekg-2" name="c_ekg" value="2" <?php echo ($daten['c_ekg'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-2">Infarkt EKG / STEMI</label>

                            <input type="radio" class="btn-check" id="c_ekg-7" name="c_ekg" value="7" <?php echo ($daten['c_ekg'] == 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-7">Linksschenkelblock</label>

                            <input type="radio" class="btn-check" id="c_ekg-71" name="c_ekg" value="71" <?php echo ($daten['c_ekg'] == 71 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-71">Rechtsschenkelblock</label>

                            <input type="radio" class="btn-check" id="c_ekg-97" name="c_ekg" value="97" <?php echo ($daten['c_ekg'] == 97 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-97">Sonstige</label>

                            <input type="radio" class="btn-check" id="c_ekg-98" name="c_ekg" value="98" <?php echo ($daten['c_ekg'] == 98 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-98">Nicht beurteilbar</label>

                            <input type="radio" class="btn-check" id="c_ekg-99" name="c_ekg" value="99" <?php echo ($daten['c_ekg'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="c_ekg-99">Kein EKG</label>
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