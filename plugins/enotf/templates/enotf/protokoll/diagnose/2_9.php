<?php
/**
 * View: enotf/protokoll/diagnose/2_9.php
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_2') ?>">
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


                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_9') ?>" class="active">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_10') ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-81" name="diagnose_weitere[]" value="81" <?php echo (in_array(81, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-81">Anaphylaxie Grad 1/2</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-82" name="diagnose_weitere[]" value="82" <?php echo (in_array(82, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-82">Anaphylaxie Grad 3/4</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-83" name="diagnose_weitere[]" value="83" <?php echo (in_array(83, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-83">Infekt / Sepsis / Schock</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-84" name="diagnose_weitere[]" value="84" <?php echo (in_array(84, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-84">Hitzeerschöpfung / Hitzschlag</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-85" name="diagnose_weitere[]" value="85" <?php echo (in_array(85, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-85">Unterkühlung / Erfrierung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-86" name="diagnose_weitere[]" value="86" <?php echo (in_array(86, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-86">akute Lumbago</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-87" name="diagnose_weitere[]" value="87" <?php echo (in_array(87, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-87">Palliative Situation</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-88" name="diagnose_weitere[]" value="88" <?php echo (in_array(88, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-88">medizinische Behandlungskompl.</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-89" name="diagnose_weitere[]" value="89" <?php echo (in_array(89, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-89">Epistaxis / HNO-Erkrankung</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-91" name="diagnose_weitere[]" value="91" <?php echo (in_array(91, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-91">urologische Erkrankung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-92" name="diagnose_weitere[]" value="92" <?php echo (in_array(92, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-92">unklare Erkrankung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-93" name="diagnose_weitere[]" value="93" <?php echo (in_array(93, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-93">akzidentelle Intoxikation</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-94" name="diagnose_weitere[]" value="94" <?php echo (in_array(94, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-94">Augenerkrankung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-99" name="diagnose_weitere[]" value="99" <?php echo (in_array(99, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-99">Keine Erkrankung / Verletzung feststellbar</label>
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