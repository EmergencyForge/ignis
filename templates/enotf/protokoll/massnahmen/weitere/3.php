<?php
/**
 * View: enotf/protokoll/massnahmen/weitere/3.php
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

$rettungstechnik = [];
if (!empty($daten['rettungstechnik'])) {
    $decoded = json_decode($daten['rettungstechnik'], true);
    if (is_array($decoded)) {
        $rettungstechnik = array_map('intval', $decoded);
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

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege') ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung') ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang') ?>" data-requires="c_zugang">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'medikamente') ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere') ?>" class="active">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere/1') ?>">
                                <span>Lagerung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere/3') ?>" class="active">
                                <span>Rettungstechnik</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere/2') ?>">
                                <span>spezielle Maßnahmen</span>
                            </a>
                            <input type="checkbox" class="btn-check" id="waerme_passiv-1" name="waerme_passiv" value="1" <?php echo ($daten['waerme_passiv'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="waerme_passiv-1">passiver Wärmeerhalt</label>

                            <input type="checkbox" class="btn-check" id="e_reposition-1" name="e_reposition" value="1" <?php echo ($daten['e_reposition'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="e_reposition-1">Reposition</label>

                            <input type="checkbox" class="btn-check" id="e_verband-1" name="e_verband" value="1" <?php echo ($daten['e_verband'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="e_verband-1">Verband</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <input type="checkbox" class="btn-check" id="rettungstechnik-1" name="rettungstechnik[]" value="1" <?php echo (in_array(1, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-1">Spineboard</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-2" name="rettungstechnik[]" value="2" <?php echo (in_array(2, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-2">KED-System</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-3" name="rettungstechnik[]" value="3" <?php echo (in_array(3, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-3">Beckenschlinge</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-4" name="rettungstechnik[]" value="4" <?php echo (in_array(4, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-4">Schaufeltrage</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-5" name="rettungstechnik[]" value="5" <?php echo (in_array(5, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-5">Vakuummatratze</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-6" name="rettungstechnik[]" value="6" <?php echo (in_array(6, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-6">SAMsplint</label>

                            <input type="checkbox" class="btn-check" id="rettungstechnik-99" name="rettungstechnik[]" value="99" <?php echo (in_array(99, $rettungstechnik) ? 'checked' : '') ?> autocomplete="off">
                            <label for="rettungstechnik-99">sonstige Immobilisation</label>
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