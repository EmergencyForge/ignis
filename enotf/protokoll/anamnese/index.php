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
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

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

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="anamnese" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                                <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2.php?enr=<?= $daten['enr'] ?>">
                                    <span>Symptome</span>
                                </a>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/1.php?enr=<?= $daten['enr'] ?>">
                                    <span>Anamnese</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="col edivi__overview-container">
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2_1.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">Symptombeginn</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label class="edivi__description">Datum</label>
                                                    <input type="text" class="w-100 form-control" value="<?= !empty($daten['symptombeginn_datum']) ? date('d.m.Y', strtotime($daten['symptombeginn_datum'])) : '' ?>" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">Zeit</label>
                                                    <input type="text" class="w-100 form-control" value="<?= $daten['symptombeginn_zeit'] ?? '' ?>" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description"></label>
                                                    <input type="text" class="w-100 form-control" value="<?php
                                                                                                            $opts = [];
                                                                                                            if (!empty($daten['symptombeginn_geschaetzt'])) $opts[] = 'geschätzt';
                                                                                                            if (!empty($daten['symptombeginn_nf'])) $opts[] = 'nicht feststellbar';
                                                                                                            echo implode(', ', $opts);
                                                                                                            ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2_2.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1">NACA</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label class="edivi__description">Initial</label>
                                                    <input type="text" class="w-100 form-control" value="<?= $naca_labels[$daten['naca_initial'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">bei Übergabe</label>
                                                    <input type="text" class="w-100 form-control" value="<?= $naca_labels[$daten['naca_uebergabe'] ?? ''] ?? '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/anamnese/1.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1">Anamnese</h5>
                                <div class="col">
                                    <div class="row my-2">
                                        <div class="col">
                                            <label for="anamnese" class="edivi__description" style="display: none;">Anamnese</label>
                                            <textarea name="anamnese" id="anamnese" class="w-100 form-control" style="height: 60vh; overflow-y: auto; resize: none; border: 0 !important;" readonly><?= $daten['anmerkungen'] ?></textarea>
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
    include __DIR__ . '/../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../assets/functions/enotf/clock.php';
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