<?php
/**
 * View: enotf/protokoll/diagnose/1_6.php
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
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="flex flex-wrap -mx-3 h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="flex-1 px-3" id="edivi__content" style="padding-left: 0">
                    <div class="flex flex-wrap -mx-3" style="margin-left: 0">
                        <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1') ?>" data-requires="diagnose_haupt" class="active">
                                <span>Diagnose (führend)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2') ?>">
                                <span>Diagnose (weitere)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '3') ?>">
                                <span>Diagnose Text</span>
                            </a>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_1') ?>">
                                <span>ZNS</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_2') ?>">
                                <span>Herz-Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_3') ?>">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_4') ?>">
                                <span>Abdomen</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_5') ?>">
                                <span>Psychiatrie</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_6') ?>" class="active">
                                <span>Stoffwechsel</span>
                            </a>


                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_9') ?>">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_10') ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="diagnose_haupt-71" name="diagnose_haupt" value="71" <?php echo ($daten['diagnose_haupt'] == 71 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-71">Hypoglykämie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-72" name="diagnose_haupt" value="72" <?php echo ($daten['diagnose_haupt'] == 72 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-72">Hyperglykämie</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-73" name="diagnose_haupt" value="73" <?php echo ($daten['diagnose_haupt'] == 73 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-73">Urämie / ANV</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-74" name="diagnose_haupt" value="74" <?php echo ($daten['diagnose_haupt'] == 74 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-74">Exsikkose</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-75" name="diagnose_haupt" value="75" <?php echo ($daten['diagnose_haupt'] == 75 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-75">bek. Dialysepflicht</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-79" name="diagnose_haupt" value="79" <?php echo ($daten['diagnose_haupt'] == 79 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-79">sonstige Erkrankung Stoffwechsel</label>
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