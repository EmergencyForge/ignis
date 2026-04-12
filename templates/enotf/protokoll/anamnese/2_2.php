<?php
/**
 * View: enotf/protokoll/anamnese/2_2.php
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

    if (count($daten) == 0) {
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

$naca_options = [
    0 => 'NACA 0 - Keine Erkrankung/Verletzung',
    1 => 'NACA I - geringfügige Störung',
    2 => 'NACA II - leichte Störung',
    3 => 'NACA III - mäßige Störung',
    4 => 'NACA IV - Lebensgefahr nicht auszuschließen',
    5 => 'NACA V - Akute Lebensgefahr',
    6 => 'NACA VI - Kreislaufstillstand',
    7 => 'NACA VII - Todesfeststellung',
];

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

<body data-bs-theme="dark" data-page="anamnese" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '1') ?>">
                                <span>Anamnese</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2') ?>" data-requires="naca_initial" class="active">
                                <span>Symptome</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '3') ?>" data-requires="elokation">
                                <span>Einsatzort</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_1') ?>">
                                <span>Symptombeginn</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_2') ?>" data-requires="naca_initial" class="active">
                                <span>NACA</span>
                            </a>
                        </div>
                        <div class="col-3 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">initial</label>
                            <?php foreach ($naca_options as $val => $label) : ?>
                                <input type="radio" class="btn-check" id="naca_initial-<?= $val ?>"
                                    name="naca_initial" value="<?= $val ?>"
                                    <?= ($daten['naca_initial'] !== null && $daten['naca_initial'] == $val ? 'checked' : '') ?>
                                    autocomplete="off">
                                <label for="naca_initial-<?= $val ?>"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-3 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">bei Übergabe</label>
                            <?php foreach ($naca_options as $val => $label) : ?>
                                <input type="radio" class="btn-check" id="naca_uebergabe-<?= $val ?>"
                                    name="naca_uebergabe" value="<?= $val ?>"
                                    <?= ($daten['naca_uebergabe'] !== null && $daten['naca_uebergabe'] == $val ? 'checked' : '') ?>
                                    autocomplete="off">
                                <label for="naca_uebergabe-<?= $val ?>"><?= $label ?></label>
                            <?php endforeach; ?>
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