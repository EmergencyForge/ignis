<?php
/**
 * View: enotf/protokoll/diagnose/2_4.php
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
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_4') ?>" class="active">
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
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-51" name="diagnose_weitere[]" value="51" <?php echo (in_array(51, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-51">akutes Abdomen</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-52" name="diagnose_weitere[]" value="52" <?php echo (in_array(52, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-52">obere GI-Blutung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-53" name="diagnose_weitere[]" value="53" <?php echo (in_array(53, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-53">untere GI-Blutung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-54" name="diagnose_weitere[]" value="54" <?php echo (in_array(54, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-54">Gallenkolik</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-55" name="diagnose_weitere[]" value="55" <?php echo (in_array(55, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-55">Nierenkolik</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-56" name="diagnose_weitere[]" value="56" <?php echo (in_array(56, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-56">Kolik allgemein</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-59" name="diagnose_weitere[]" value="59" <?php echo (in_array(59, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-59">sonstige Erkrankung Abdomen</label>
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