<?php
/**
 * View: enotf/protokoll/erstbefund/neurologie/4.php
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atemwege') ?>" data-requires="awfrei_1,zyanose_1">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'atmung') ?>" data-requires="b_symptome,b_auskult">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'kreislauf') ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie') ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3" class="active">
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
                                id="neuro-ohne-path"
                                data-quickfill='{"d_bewusstsein": 1, "d_ex_1": 1, "d_pupillenw_1": 2, "d_pupillenw_2": 2, "d_lichtreakt_1": 1, "d_lichtreakt_2": 1, "d_gcs_1": 0, "d_gcs_2": 0, "d_gcs_3": 0}'
                                autocomplete="off">
                            <label for="neuro-ohne-path" class="edivi__unauffaellig">ohne path. Befund</label>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie/1') ?>" data-requires="d_bewusstsein">
                                <span>Bewusstseinslage</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie/2') ?>" data-requires="d_ex_1">
                                <span>Extremitätenbewegung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie/3') ?>" data-requires="d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2">
                                <span>Pupillen</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'erstbefund', 'neurologie/4') ?>" data-requires="d_gcs_1,d_gcs_2,d_gcs_3" class="active">
                                <span>GCS</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <label class="edivi__interactbutton-text">Augen öffnen</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-0" name="d_gcs_1" value="0" <?php echo ($daten['d_gcs_1'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-0">spontan</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-1" name="d_gcs_1" value="1" <?php echo ($daten['d_gcs_1'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-1">auf Aufforderung</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-2" name="d_gcs_1" value="2" <?php echo ($daten['d_gcs_1'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-2">auf Schmerzreiz</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-3" name="d_gcs_1" value="3" <?php echo ($daten['d_gcs_1'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-3">kein Öffnen</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <label class="edivi__interactbutton-text">Beste verbale Reaktion</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-0" name="d_gcs_2" value="0" <?php echo ($daten['d_gcs_2'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-0">orientiert</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-1" name="d_gcs_2" value="1" <?php echo ($daten['d_gcs_2'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-1">desorientiert</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-2" name="d_gcs_2" value="2" <?php echo ($daten['d_gcs_2'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-2">inadäquate Äußerungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-3" name="d_gcs_2" value="3" <?php echo ($daten['d_gcs_2'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-3">unverständliche Laute</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-4" name="d_gcs_2" value="4" <?php echo ($daten['d_gcs_2'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-4">keine Reaktion</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton px-3">
                            <label class="edivi__interactbutton-text">Beste motorische Reaktion</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-0" name="d_gcs_3" value="0" <?php echo ($daten['d_gcs_3'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-0">folgt Aufforderung</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-1" name="d_gcs_3" value="1" <?php echo ($daten['d_gcs_3'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-1">gezielte Abwehrbewegungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-2" name="d_gcs_3" value="2" <?php echo ($daten['d_gcs_3'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-2">ungezielte Abwehrbewegungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-3" name="d_gcs_3" value="3" <?php echo ($daten['d_gcs_3'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-3">Beugesynergismen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-4" name="d_gcs_3" value="4" <?php echo ($daten['d_gcs_3'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-4">Strecksynergismen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-5" name="d_gcs_3" value="5" <?php echo ($daten['d_gcs_3'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-5">keine Reaktion</label>
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