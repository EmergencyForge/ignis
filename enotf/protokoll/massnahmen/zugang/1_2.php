<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/zugang_helpers.php';

use App\Auth\Permissions;

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

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_zugang" class="active">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/weitere/index.php?enr=<?= $daten['enr'] ?>">
                                <span>Weitere</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Zugang</span>
                            </a>
                            <input type="checkbox" class="btn-check" id="c_zugang-0" name="c_zugang" value="0"
                                <?php echo (isset($daten['c_zugang']) && $daten['c_zugang'] === '0') ? 'checked' : '' ?>
                                autocomplete="off">
                            <label for="c_zugang-0">Kein Zugang</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_1.php?enr=<?= $daten['enr'] ?>">
                                <span>PVK</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>intraossär</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2_1.php?enr=<?= $daten['enr'] ?>">
                                <span>Tibia proximal</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2_3.php?enr=<?= $daten['enr'] ?>">
                                <span>Tibia distal</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2_2.php?enr=<?= $daten['enr'] ?>">
                                <span>Humerus proximal</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2_4.php?enr=<?= $daten['enr'] ?>">
                                <span>Sternum</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/1_2_5.php?enr=<?= $daten['enr'] ?>">
                                <span>anderer Ort</span>
                            </a>
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
    <script>
        $(document).ready(function() {
            $('#c_zugang-0').on('change', function() {
                if ($(this).is(':checked')) {
                    $.ajax({
                        url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                        type: 'POST',
                        data: {
                            enr: '<?= $enr ?>',
                            field: 'c_zugang',
                            value: '0'
                        },
                        success: function(response) {
                            showToast('Alle Zugänge entfernt', 'success');

                        }
                    });
                }
            });
        });
    </script>
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