<?php
/**
 * View: enotf/protokoll/anamnese/index.php
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

$elokation_labels = [
    1 => 'Wohnung',
    2 => 'Arbeitsplatz',
    3 => 'Altenheim',
    4 => 'öffentlicher Raum',
    5 => 'Arztpraxis',
    6 => 'Straße',
    7 => 'Krankenhaus',
    8 => 'Massenveranstaltung',
    9 => 'Bildungseinrichtung',
    10 => 'Sportstätte',
    11 => 'Geburtshaus/-einrichtung',
    98 => 'Sonstige',
    99 => 'nicht dokumentiert',
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
        <div class="w-full" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col-2 flex flex-col edivi__interactbutton-more">
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '1') ?>">
                                    <span>Anamnese</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2') ?>" data-requires="naca_initial">
                                    <span>Symptome</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '3') ?>" data-requires="elokation">
                                    <span>Einsatzort</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="col edivi__overview-container">
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '1') ?>" style="cursor:pointer">
                                <h5 class="text-white px-2 py-1">Anamnese</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="anamnese" class="edivi__description" style="display: none;">Anamnese</label>
                                            <textarea name="anamnese" id="anamnese" class="w-full form-control" style="height: 50vh; overflow-y: auto; resize: none; border: 0 !important;" readonly><?= $daten['anmerkungen'] ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_1') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1">Symptome</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label class="edivi__description">Symptombeginn</label>
                                                    <input type="text" class="w-full form-control" value="<?php
                                                                                                            $sb_datum = !empty($daten['symptombeginn_datum']) ? date('d.m.Y', strtotime($daten['symptombeginn_datum'])) : '';
                                                                                                            $sb_zeit = $daten['symptombeginn_zeit'] ?? '';
                                                                                                            $sb_opts = [];
                                                                                                            if (!empty($daten['symptombeginn_geschaetzt'])) $sb_opts[] = 'geschätzt';
                                                                                                            if (!empty($daten['symptombeginn_nf'])) $sb_opts[] = 'nicht feststellbar';

                                                                                                            $sb_datetime = trim($sb_datum . ' ' . $sb_zeit);
                                                                                                            if ($sb_datetime !== '' && !empty($sb_opts)) {
                                                                                                                echo $sb_datetime . ' (' . implode(', ', $sb_opts) . ')';
                                                                                                            } elseif ($sb_datetime !== '') {
                                                                                                                echo $sb_datetime;
                                                                                                            } else {
                                                                                                                echo implode(', ', $sb_opts);
                                                                                                            }
                                                                                                            ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2_2') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1">NACA</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label class="edivi__description">Initial</label>
                                                    <input type="text" name="naca_initial_display" class="w-full form-control edivi__input-check" value="<?= $naca_labels[$daten['naca_initial'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">bei Übergabe</label>
                                                    <input type="text" class="w-full form-control" value="<?= $naca_labels[$daten['naca_uebergabe'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '3') ?>" style="cursor:pointer">
                                        <h5 class="text-white px-2 py-1">Einsatzort <i id="icon-elokation_display" class="fa-solid fa-circle-exclamation" style="color:#d91425; margin-left:4px; display:none;"></i></h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label class="edivi__description" style="display:none">Einsatzort</label>
                                                    <input type="text" name="elokation_display" class="w-full form-control edivi__input-check" value="<?= $elokation_labels[$daten['elokation'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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