<?php
/**
 * View: enotf/protokoll/diagnose/2_2.php
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
// Decode existing diagnose_weitere from JSON
$diagnose_weitere = [];
if (!empty($daten['diagnose_weitere'])) {
    $decoded = json_decode($daten['diagnose_weitere'], true);
    if (is_array($decoded)) {
        $diagnose_weitere = array_map('intval', $decoded);
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
    include dirname(__DIR__, 6) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include dirname(__DIR__, 6) . '/assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include dirname(__DIR__, 6) . '/assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1') ?>" data-requires="diagnose_haupt">
                                <span>Diagnose (führend)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2') ?>" class="active">
                                <span>Diagnose (weitere)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '3') ?>">
                                <span>Diagnose Text</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_1') ?>">
                                <span>ZNS</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_2') ?>" class="active">
                                <span>Herz-Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_3') ?>">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_4') ?>">
                                <span>Abdomen</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_5') ?>">
                                <span>Psychiatrie</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_6') ?>">
                                <span>Stoffwechsel</span>
                            </a>


                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_9') ?>">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_10') ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-11" name="diagnose_weitere[]" value="11" <?php echo (in_array(11, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-11">ACS / NSTEMI</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-12" name="diagnose_weitere[]" value="12" <?php echo (in_array(12, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-12">ACS / STEMI</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-13" name="diagnose_weitere[]" value="13" <?php echo (in_array(13, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-13">Kardiogener Schock</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-14" name="diagnose_weitere[]" value="14" <?php echo (in_array(14, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-14">tachykarde Arrhythmie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-15" name="diagnose_weitere[]" value="15" <?php echo (in_array(15, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-15">bradykarde Arrhythmie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-16" name="diagnose_weitere[]" value="16" <?php echo (in_array(16, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-16">Schrittmacher-/ICD Fehlfunktion</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-17" name="diagnose_weitere[]" value="17" <?php echo (in_array(17, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-17">Lungenembolie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-18" name="diagnose_weitere[]" value="18" <?php echo (in_array(18, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-18">Lungenödem</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-19" name="diagnose_weitere[]" value="19" <?php echo (in_array(19, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-19">hypertensiver Notfall</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-20" name="diagnose_weitere[]" value="20" <?php echo (in_array(20, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-20">Aortenaneurysma</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-21" name="diagnose_weitere[]" value="21" <?php echo (in_array(21, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-21">Hypotonie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-22" name="diagnose_weitere[]" value="22" <?php echo (in_array(22, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-22">Synkope</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-23" name="diagnose_weitere[]" value="23" <?php echo (in_array(23, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-23">Thrombose / art. Verschluss</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-24" name="diagnose_weitere[]" value="24" <?php echo (in_array(24, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-24">Herz-Kreislauf-Stillstand</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-25" name="diagnose_weitere[]" value="25" <?php echo (in_array(25, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-25">Schock unklarer Genese</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-26" name="diagnose_weitere[]" value="26" <?php echo (in_array(26, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-26">unklarer Thoraxschmerz</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-27" name="diagnose_weitere[]" value="27" <?php echo (in_array(27, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-27">orthostatische Fehlregulation</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-28" name="diagnose_weitere[]" value="28" <?php echo (in_array(28, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-28">hypertensive Krise / Entgleisung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-29" name="diagnose_weitere[]" value="29" <?php echo (in_array(29, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-29">sonstige Erkrankung Herz-Kreislauf</label>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include dirname(__DIR__, 6) . '/assets/functions/enotf/notify.php';
    include dirname(__DIR__, 6) . '/assets/functions/enotf/field_checks.php';
    include dirname(__DIR__, 6) . '/assets/functions/enotf/clock.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/js/modules/enotf-diagnose.js"></script>
    <script>
    initEnotfDiagnosePage({
        basePath:      '<?= BASE_PATH ?>',
        enr:           '<?= $enr ?>',
        initialValues: <?= json_encode($diagnose_weitere) ?>,
        readonly:      <?= $ist_freigegeben ? 'true' : 'false' ?>,
    });
    </script>
    <?php if ($ist_freigegeben) : ?>
        <script>
            var formElements = document.querySelectorAll('input, textarea');
            var selectElements2 = document.querySelectorAll('select');
            var inputElements2 = document.querySelectorAll('.btn-check');
            var inputElements3 = document.querySelectorAll('.');

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