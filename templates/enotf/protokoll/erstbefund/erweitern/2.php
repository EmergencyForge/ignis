<?php
/**
 * View: enotf/protokoll/erstbefund/erweitern/2.php
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
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>" class="active">
                                <span>Erweitern</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'ekg') ?>" data-requires="c_ekg">
                                <span>EKG-Befund</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" data-requires="psych">
                                <span>psych. Zustand</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                <span>Messwerte</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <input type="checkbox"
                                class="btn-check"
                                id="erweitern-ohne-path"
                                data-quickfill='{"v_muster_k": 1, "v_muster_w": 1, "v_muster_t": 1, "v_muster_a": 1, "v_muster_al": 1, "v_muster_bl": 1}'
                                autocomplete="off">
                            <label for="erweitern-ohne-path" class="edivi__unauffaellig">keine</label>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern/1') ?>">
                                <span>Verletzungen</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern/2') ?>" class="active">
                                <span>Schmerzen</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="sz_nrs-0" name="sz_nrs" value="0" <?php echo ($daten['sz_nrs'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-0">keine</label>

                            <input type="radio" class="btn-check" id="sz_nrs-1" name="sz_nrs" value="1" <?php echo ($daten['sz_nrs'] === 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-1">1</label>

                            <input type="radio" class="btn-check" id="sz_nrs-2" name="sz_nrs" value="2" <?php echo ($daten['sz_nrs'] === 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-2">2</label>

                            <input type="radio" class="btn-check" id="sz_nrs-3" name="sz_nrs" value="3" <?php echo ($daten['sz_nrs'] === 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-3">3</label>

                            <input type="radio" class="btn-check" id="sz_nrs-4" name="sz_nrs" value="4" <?php echo ($daten['sz_nrs'] === 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-4">4</label>

                            <input type="radio" class="btn-check" id="sz_nrs-5" name="sz_nrs" value="5" <?php echo ($daten['sz_nrs'] === 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-5">5</label>

                            <input type="radio" class="btn-check" id="sz_nrs-6" name="sz_nrs" value="6" <?php echo ($daten['sz_nrs'] === 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-6">6</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="sz_nrs-7" name="sz_nrs" value="7" <?php echo ($daten['sz_nrs'] === 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-7">7</label>

                            <input type="radio" class="btn-check" id="sz_nrs-8" name="sz_nrs" value="8" <?php echo ($daten['sz_nrs'] === 8 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-8">8</label>

                            <input type="radio" class="btn-check" id="sz_nrs-9" name="sz_nrs" value="9" <?php echo ($daten['sz_nrs'] === 9 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-9">9</label>

                            <input type="radio" class="btn-check" id="sz_nrs-10" name="sz_nrs" value="10" <?php echo ($daten['sz_nrs'] === 10 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-10">10</label>

                            <input type="radio" class="btn-check" id="sz_nrs-98" name="sz_nrs" value="98" <?php echo ($daten['sz_nrs'] === 98 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-98">Nicht erhoben</label>

                            <input type="radio" class="btn-check" id="sz_nrs-99" name="sz_nrs" value="99" <?php echo ($daten['sz_nrs'] === 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_nrs-99">Nicht beurteilbar</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="sz_toleranz_1-1" name="sz_toleranz_1" value="1" <?php echo ($daten['sz_toleranz_1'] === 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_toleranz_1-1">Tolerabel</label>

                            <input type="radio" class="btn-check" id="sz_toleranz_1-2" name="sz_toleranz_1" value="2" <?php echo ($daten['sz_toleranz_1'] === 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="sz_toleranz_1-2">Nicht tolerabel</label>
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