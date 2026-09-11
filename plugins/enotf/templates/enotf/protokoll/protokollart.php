<?php
/**
 * View: enotf/protokoll/protokollart.php
 */


use App\Auth\Permissions;
use Plugin\Enotf\Helpers\EnotfUrl;
use App\Helpers\Redirects;
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

if ($ist_freigegeben) {
    header("Location: " . EnotfUrl::protokoll($daten['enr']));
    exit();
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');
$defaultUrl = EnotfUrl::protokoll($daten['enr']);

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rdprot'])) {
        Edivi::where('enr', $_GET['enr'])->update(['prot_by' => 0]);
        Redirects::redirect($defaultUrl, []);
        exit();
    }

    if (isset($_POST['naprot'])) {
        Edivi::where('enr', $_GET['enr'])->update(['prot_by' => 1]);
        Redirects::redirect($defaultUrl, []);
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include dirname(__DIR__, 5) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <div class="col" id="edivi__content">
                    <div class="hr my-5" style="color:transparent"></div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton w-100 flex align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="rdprot" name="rdprot"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NF</span> Notfallprotokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton w-100 flex align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="naprot" name="naprot"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NA</span> Notarztprotokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col text-center">
                            <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>" class="edivi__nidabutton-secondary w-100" style="display:inline-block">zurück</a>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include dirname(__DIR__, 5) . '/assets/functions/enotf/notify.php';
    ?>
    <script>
        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        modalCloseButton.addEventListener('click', function() {
            freigeberInput.value = '';
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>