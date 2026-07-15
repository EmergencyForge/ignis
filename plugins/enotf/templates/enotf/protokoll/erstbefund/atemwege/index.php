<?php
/**
 * View: enotf/protokoll/erstbefund/atemwege/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;

use Plugin\Enotf\Helpers\EnotfUrl;
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
    include dirname(__DIR__, 7) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="erstbefund" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include dirname(__DIR__, 7) . '/assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include dirname(__DIR__, 7) . '/assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege') ?>" data-requires="awfrei_1,zyanose_1" class="active">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atmung') ?>" data-requires="b_symptome,b_auskult">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'kreislauf') ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie') ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3">
                                <span>Neurologie</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'erweitern') ?>">
                                <span>Erweitern</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'ekg') ?>" data-requires="c_ekg">
                                <span>EKG-Befund</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'psychisch') ?>" data-requires="psych">
                                <span>psych. Zustand</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'messwerte') ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz">
                                <span>Messwerte</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <input type="checkbox"
                                class="btn-check"
                                id="atemwege-ohne-path"
                                data-quickfill='{"awfrei_1": 1, "zyanose_1": 1}'
                                autocomplete="off">
                            <label for="atemwege-ohne-path" class="edivi__unauffaellig">ohne path. Befund</label>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege/1') ?>" data-requires="awfrei_1">
                                <span>Atemwegszustand</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege/2') ?>" data-requires="zyanose_1">
                                <span>Zyanose</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include dirname(__DIR__, 7) . '/assets/functions/enotf/notify.php';
    include dirname(__DIR__, 7) . '/assets/functions/enotf/field_checks.php';
    include dirname(__DIR__, 7) . '/assets/functions/enotf/clock.php';
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