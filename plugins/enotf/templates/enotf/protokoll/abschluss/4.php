<?php
/**
 * View: enotf/protokoll/abschluss/4.php
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

$prot_url = "https://" . SYSTEM_URL . \Plugin\Enotf\Helpers\EnotfUrl::protokoll($enr);

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

<body data-bs-theme="dark" data-page="abschluss" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '1') ?>" data-requires="ebesonderheiten">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '2') ?>" data-requires="na_nachf">
                                <span>Nachforderung NA</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '4') ?>" data-requires="rea_status" class="active">
                                <span>Reanimationssituation</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3') ?>">
                                <span>Übergabe</span>
                            </a>
                            <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient">
                                <span>An Leitstelle senden</span>
                            </a>
                        </div>
                        <?php
                        $reaDaten = $daten;
                        $reaGesperrt = $ist_freigegeben;
                        $reaCol = 'w-2/12';
                        $reaColWide = 'w-3/12';
                        include dirname(__DIR__, 2) . '/_partials/reanimation.php';
                        ?>
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
    <script>
        function sendPatientToDispatch(e) {
            e.preventDefault();
            const syncIcon = document.getElementById('pat-sync-icon');
            const syncIconEl = syncIcon ? syncIcon.querySelector('i') : null;
            if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
            fetch('<?= BASE_PATH ?>api/enotf/patient-sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enr: <?= json_encode($enr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
                } else {
                    if (syncIconEl) syncIconEl.style.color = '#dc3545';
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(() => {
                if (syncIconEl) syncIconEl.style.color = '#dc3545';
                alert('Verbindungsfehler beim Senden.');
            });
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>