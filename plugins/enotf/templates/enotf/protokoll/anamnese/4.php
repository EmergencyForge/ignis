<?php
/**
 * View: enotf/protokoll/anamnese/4.php
 */


use App\Auth\Permissions;

use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Models\Edivi;
$daten = array();

if (isset($_GET['enr'])) {
    $daten = Edivi::where('enr', $_GET['enr'])->first();

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
    $SITE_TITLE = "[#" . e($daten['enr']) . "] &rsaquo; eNOTF";
    include dirname(__DIR__, 6) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="anamnese" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include dirname(__DIR__, 6) . '/assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include dirname(__DIR__, 6) . '/assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '1') ?>">
                                    <span>Anamnese</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '2') ?>" data-requires="naca_initial">
                                    <span>Symptome</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '4') ?>" class="active">
                                    <span>AZ vor Ereignis</span>
                                </a>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'anamnese', '3') ?>" data-requires="elokation">
                                    <span>Einsatzort</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="w-6/12 d-flex flex-column edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="az_vor_ereignis-1" name="az_vor_ereignis" value="1" <?= ($daten['az_vor_ereignis'] ?? '') == 1 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-1">ohne Vorerkrankungen</label>

                            <input type="radio" class="btn-check" id="az_vor_ereignis-2" name="az_vor_ereignis" value="2" <?= ($daten['az_vor_ereignis'] ?? '') == 2 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-2">Vorerkrankungen ohne nennenswerte Einschränkung des tägl. Lebens</label>

                            <input type="radio" class="btn-check" id="az_vor_ereignis-3" name="az_vor_ereignis" value="3" <?= ($daten['az_vor_ereignis'] ?? '') == 3 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-3">Vorerkrankungen mit nennenswerter Einschränkung des tägl. Lebens</label>

                            <input type="radio" class="btn-check" id="az_vor_ereignis-4" name="az_vor_ereignis" value="4" <?= ($daten['az_vor_ereignis'] ?? '') == 4 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-4">normales tägl. Leben unmöglich</label>

                            <input type="radio" class="btn-check" id="az_vor_ereignis-5" name="az_vor_ereignis" value="5" <?= ($daten['az_vor_ereignis'] ?? '') == 5 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-5">Pat. wird in den nächsten 24h sterben, mit und ohne med. Hilfe</label>

                            <input type="radio" class="btn-check" id="az_vor_ereignis-99" name="az_vor_ereignis" value="99" <?= ($daten['az_vor_ereignis'] ?? '') == 99 ? 'checked' : '' ?> autocomplete="off">
                            <label for="az_vor_ereignis-99">unbekannt</label>
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
    <?php if ($ist_freigegeben) : ?><script src="<?= BASE_PATH ?>assets/js/enotf-lock.js"></script><?php endif; ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>