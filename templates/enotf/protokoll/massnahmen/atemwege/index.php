<?php
/**
 * View: enotf/protokoll/massnahmen/atemwege/index.php
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege') ?>" data-requires="awsicherung_neu" class="active">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere') ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege/1') ?>" data-requires="awsicherung_neu">
                                <span>Atemwegssicherung</span>
                            </a>

                            <input type="checkbox" class="btn-check" id="awsicherung_1-1" name="awsicherung_1" value="1" <?php echo ($daten['awsicherung_1'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_1-1">Atemwege freim.</label>

                            <input type="checkbox" class="btn-check" id="awsicherung_2-1" name="awsicherung_2" value="1" <?php echo ($daten['awsicherung_2'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="awsicherung_2-1">Absaugen</label>

                            <input type="checkbox" class="btn-check" id="entlastungspunktion-1" name="entlastungspunktion" value="1" <?php echo ($daten['entlastungspunktion'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="entlastungspunktion-1">Entlastungspunktion</label>

                            <input type="checkbox" class="btn-check" id="hws_immo-1" name="hws_immo" value="1" <?php echo ($daten['hws_immo'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="hws_immo-1">HWS-Immobilisation</label>
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