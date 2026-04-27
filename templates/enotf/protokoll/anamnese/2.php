<?php
/**
 * View: enotf/protokoll/anamnese/2.php
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

$naca_labels = [
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
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
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
                            <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_1') ?>">
                                    <span>Symptombeginn</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_2') ?>" data-requires="naca_initial">
                                    <span>NACA</span>
                                </a>
                            </div>
                        <?php endif; ?>
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