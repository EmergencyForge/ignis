<?php
/**
 * View: enotf/protokoll/diagnose/1_9.php
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
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 flex flex-col edivi__interactbutton-more">
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
                        <div class="col-2 flex flex-col edivi__interactbutton-more">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_6') ?>">
                                <span>Stoffwechsel</span>
                            </a>


                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_9') ?>" class="active">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1_10') ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="col-2 flex flex-col edivi__interactbutton">
                            <input type="radio" class="btn-check" id="diagnose_haupt-81" name="diagnose_haupt" value="81" <?php echo ($daten['diagnose_haupt'] == 81 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-81">Anaphylaxie Grad 1/2</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-82" name="diagnose_haupt" value="82" <?php echo ($daten['diagnose_haupt'] == 82 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-82">Anaphylaxie Grad 3/4</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-83" name="diagnose_haupt" value="83" <?php echo ($daten['diagnose_haupt'] == 83 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-83">Infekt / Sepsis / Schock</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-84" name="diagnose_haupt" value="84" <?php echo ($daten['diagnose_haupt'] == 84 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-84">Hitzeerschöpfung / Hitzschlag</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-85" name="diagnose_haupt" value="85" <?php echo ($daten['diagnose_haupt'] == 85 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-85">Unterkühlung / Erfrierung</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-86" name="diagnose_haupt" value="86" <?php echo ($daten['diagnose_haupt'] == 86 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-86">akute Lumbago</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-87" name="diagnose_haupt" value="87" <?php echo ($daten['diagnose_haupt'] == 87 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-87">Palliative Situation</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-88" name="diagnose_haupt" value="88" <?php echo ($daten['diagnose_haupt'] == 88 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-88">medizinische Behandlungskompl.</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-89" name="diagnose_haupt" value="89" <?php echo ($daten['diagnose_haupt'] == 89 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-89">Epistaxis / HNO-Erkrankung</label>
                        </div>
                        <div class="col-2 flex flex-col edivi__interactbutton">
                            <input type="radio" class="btn-check" id="diagnose_haupt-91" name="diagnose_haupt" value="91" <?php echo ($daten['diagnose_haupt'] == 91 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-91">urologische Erkrankung</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-92" name="diagnose_haupt" value="92" <?php echo ($daten['diagnose_haupt'] == 92 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-92">unklare Erkrankung</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-93" name="diagnose_haupt" value="93" <?php echo ($daten['diagnose_haupt'] == 93 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-93">akzidentelle Intoxikation</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-94" name="diagnose_haupt" value="94" <?php echo ($daten['diagnose_haupt'] == 94 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-94">Augenerkrankung</label>

                            <input type="radio" class="btn-check" id="diagnose_haupt-99" name="diagnose_haupt" value="99" <?php echo ($daten['diagnose_haupt'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_haupt-99">Keine Erkrankung / Verletzung feststellbar</label>
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