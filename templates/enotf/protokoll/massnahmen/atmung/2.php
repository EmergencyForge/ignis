<?php
/**
 * View: enotf/protokoll/massnahmen/atmung/2.php
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
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege') ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung') ?>" data-requires="b_beatmung" class="active">
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
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung/1') ?>" data-requires="b_beatmung">
                                <span>Beatmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung/2') ?>" class="active">
                                <span>O2-Gabe</span>
                            </a>
                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton-more">
                            <input type="radio" class="btn-check" id="o2gabe-0" name="o2gabe" value="0" <?php echo ($daten['o2gabe'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-0">keine</label>

                            <input type="radio" class="btn-check" id="o2gabe-1" name="o2gabe" value="1" <?php echo ($daten['o2gabe'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-1">1 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-2" name="o2gabe" value="2" <?php echo ($daten['o2gabe'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-2">2 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-3" name="o2gabe" value="3" <?php echo ($daten['o2gabe'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-3">3 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-4" name="o2gabe" value="4" <?php echo ($daten['o2gabe'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-4">4 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-5" name="o2gabe" value="5" <?php echo ($daten['o2gabe'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-5">5 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-6" name="o2gabe" value="6" <?php echo ($daten['o2gabe'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-6">6 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-7" name="o2gabe" value="7" <?php echo ($daten['o2gabe'] == 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-7">7 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-8" name="o2gabe" value="8" <?php echo ($daten['o2gabe'] == 8 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-8">8 L/min</label>
                        </div>
                        <div class="col-1 d-flex flex-column edivi__interactbutton-more">
                            <input type="radio" class="btn-check" id="o2gabe-9" name="o2gabe" value="9" <?php echo ($daten['o2gabe'] == 9 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-9">9 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-10" name="o2gabe" value="10" <?php echo ($daten['o2gabe'] == 10 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-10">10 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-11" name="o2gabe" value="11" <?php echo ($daten['o2gabe'] == 11 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-11">11 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-12" name="o2gabe" value="12" <?php echo ($daten['o2gabe'] == 12 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-12">12 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-13" name="o2gabe" value="13" <?php echo ($daten['o2gabe'] == 13 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-13">13 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-14" name="o2gabe" value="14" <?php echo ($daten['o2gabe'] == 14 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-14">14 L/min</label>

                            <input type="radio" class="btn-check" id="o2gabe-15" name="o2gabe" value="15" <?php echo ($daten['o2gabe'] == 15 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe-15">15 L/min</label>
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