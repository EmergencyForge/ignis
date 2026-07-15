<?php
/**
 * View: enotf/protokoll/diagnose/2_3.php
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_3') ?>" class="active">
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
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-31" name="diagnose_weitere[]" value="31" <?php echo (in_array(31, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-31">Asthma (Anfall)</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-32" name="diagnose_weitere[]" value="32" <?php echo (in_array(32, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-32">Status Asthmaticus</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-33" name="diagnose_weitere[]" value="33" <?php echo (in_array(33, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-33">exacerbierte COPD</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-34" name="diagnose_weitere[]" value="34" <?php echo (in_array(34, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-34">Aspiration</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-35" name="diagnose_weitere[]" value="35" <?php echo (in_array(35, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-35">Pneumonie / Bronchitis</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-36" name="diagnose_weitere[]" value="36" <?php echo (in_array(36, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-36">Hyperventilation</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-37" name="diagnose_weitere[]" value="37" <?php echo (in_array(37, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-37">Spontanpneumothorax</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-38" name="diagnose_weitere[]" value="38" <?php echo (in_array(38, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-38">Hämoptysis</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-39" name="diagnose_weitere[]" value="39" <?php echo (in_array(39, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-39">Dyspnoe unklarer Ursache</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-49" name="diagnose_weitere[]" value="49" <?php echo (in_array(49, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-49">sonstige Erkrankung Atmung</label>
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