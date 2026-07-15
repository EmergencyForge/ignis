<?php
/**
 * View: enotf/protokoll/massnahmen/zugang/1_2_2.php
 *
 * @var \PDO $pdo
 */

require_once dirname(__DIR__, 7) . '/assets/functions/enotf/zugang_helpers.php';

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

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include dirname(__DIR__, 7) . '/assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atemwege') ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'atmung') ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang') ?>" data-requires="c_zugang" class="active">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'medikamente') ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'weitere') ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1') ?>" class="active">
                                <span>Zugang</span>
                            </a>
                            <input type="checkbox" class="btn-check" id="c_zugang-0" name="c_zugang" value="0"
                                <?php echo (isset($daten['c_zugang']) && $daten['c_zugang'] === '0') ? 'checked' : '' ?>
                                autocomplete="off">
                            <label for="c_zugang-0">Kein Zugang</label>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_1') ?>">
                                <span>PVK</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2') ?>" class="active">
                                <span>intraossär</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_1') ?>">
                                <span>Tibia proximal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_3') ?>">
                                <span>Tibia distal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_2') ?>" class="active">
                                <span>Humerus proximal</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_4') ?>">
                                <span>Sternum</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'zugang/1_2_5') ?>">
                                <span>anderer Ort</span>
                            </a>
                        </div>
                        <div class="w-1/12 d-flex flex-column edivi__interactbutton px-3">
                            <label class="edivi__interactbutton-text">links</label>

                            <?php
                            $groessen = ['15mm', '25mm', '45mm'];
                            $currentLeftZugang = null;

                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'io' && $zugang['ort'] === 'Humerus proximal' && $zugang['seite'] === 'links') {
                                    $currentLeftZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $groesse):
                                $radioId = "c_zugang-io-humerusproximal-l-" . ($index + 1);
                                $zugangData = [
                                    'art' => 'io',
                                    'groesse' => $groesse,
                                    'ort' => 'Humerus proximal',
                                    'seite' => 'links'
                                ];
                                $isChecked = ($currentLeftZugang && $currentLeftZugang['groesse'] === $groesse);
                            ?>
                                <input type="checkbox" class="btn-check zugang-checkbox"
                                    id="<?= $radioId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="io-humerusproximal-links"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $radioId ?>" class="edivi__zugang-<?= $groesse ?>"><?= $groesse ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="w-1/12 d-flex flex-column edivi__interactbutton px-3">
                            <label class="edivi__interactbutton-text">rechts</label>

                            <?php
                            $currentRightZugang = null;

                            foreach ($currentZugaenge as $zugang) {
                                if ($zugang['art'] === 'io' && $zugang['ort'] === 'Humerus proximal' && $zugang['seite'] === 'rechts') {
                                    $currentRightZugang = $zugang;
                                    break;
                                }
                            }

                            foreach ($groessen as $index => $groesse):
                                $radioId = "c_zugang-io-humerusproximal-r-" . ($index + 1);
                                $zugangData = [
                                    'art' => 'io',
                                    'groesse' => $groesse,
                                    'ort' => 'Humerus proximal',
                                    'seite' => 'rechts'
                                ];
                                $isChecked = ($currentRightZugang && $currentRightZugang['groesse'] === $groesse);
                            ?>
                                <input type="checkbox" class="btn-check zugang-checkbox"
                                    id="<?= $radioId ?>"
                                    name="zugang_selection"
                                    data-zugang='<?= htmlspecialchars(json_encode($zugangData), ENT_QUOTES) ?>'
                                    data-location="io-humerusproximal-rechts"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    autocomplete="off">
                                <label for="<?= $radioId ?>" class="edivi__zugang-<?= $groesse ?>"><?= $groesse ?></label>
                            <?php endforeach; ?>
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
    <script src="<?= BASE_PATH ?>assets/js/modules/enotf-zugang.js"></script>
    <script>
    initEnotfZugangPage({
        basePath: '<?= BASE_PATH ?>',
        enr:      '<?= $enr ?>',
        art:      'io',
        ort:      'Humerus proximal',
    });
    </script>
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