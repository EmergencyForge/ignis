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

$ebesonderheiten = [];
if (!empty($daten['ebesonderheiten'])) {
    $decoded = json_decode($daten['ebesonderheiten'], true);
    if (is_array($decoded)) {
        $ebesonderheiten = array_map('intval', $decoded);
    }
}

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

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

<body data-bs-theme="dark" data-page="abschluss" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" data-requires="ebesonderheiten" class="active">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <?php if ($daten['prot_by'] != 1) : ?>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/2.php?enr=<?= $daten['enr'] ?>" data-requires="na_nachf">
                                    <span>Nachforderung NA</span>
                                </a>
                            <?php endif; ?>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3.php?enr=<?= $daten['enr'] ?>">
                                <span>Übergabe</span>
                            </a>
                            <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient">
                                <span>An Leitstelle senden</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="ebesonderheiten-1" name="ebesonderheiten[]" value="1" <?php echo (in_array(1, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-1" class="edivi__unauffaellig">keine</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-2" name="ebesonderheiten[]" value="2" <?php echo (in_array(2, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-2">nächste geeignete Klinik nicht aufnahmebereit</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-3" name="ebesonderheiten[]" value="3" <?php echo (in_array(3, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-3">Patient lehnt indizierte Maßnahmen ab</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-4" name="ebesonderheiten[]" value="4" <?php echo (in_array(4, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-4">bewusster Therapieverzicht</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-5" name="ebesonderheiten[]" value="5" <?php echo (in_array(5, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-5">Patient lehnt Transport ab</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-6" name="ebesonderheiten[]" value="6" <?php echo (in_array(6, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-6">Vorsorgliche Bereitstellung</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-7" name="ebesonderheiten[]" value="7" <?php echo (in_array(7, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-7">Zwangsunterbringung</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-8" name="ebesonderheiten[]" value="8" <?php echo (in_array(8, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-8">aufwändige technische Rettung</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-9" name="ebesonderheiten[]" value="9" <?php echo (in_array(9, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-9">Einsatz mit LNA/OrgL</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="ebesonderheiten-10" name="ebesonderheiten[]" value="10" <?php echo (in_array(10, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-10">mehrere Patienten</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-11" name="ebesonderheiten[]" value="11" <?php echo (in_array(11, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-11">MANV</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-12" name="ebesonderheiten[]" value="12" <?php echo (in_array(12, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-12">kein Notarzt in angemessener Zeit verfügbar</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-13" name="ebesonderheiten[]" value="13" <?php echo (in_array(13, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-13">erschwerter Patientenzugang</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-14" name="ebesonderheiten[]" value="14" <?php echo (in_array(14, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-14">verzögerte Patientenübergabe</label>

                            <input type="checkbox" class="btn-check" id="ebesonderheiten-99" name="ebesonderheiten[]" value="99" <?php echo (in_array(99, $ebesonderheiten) ? 'checked' : '') ?> autocomplete="off">
                            <label for="ebesonderheiten-99">Sonstige</label>
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
    <script>
        function sendPatientToDispatch(e) {
            e.preventDefault();
            const syncIcon = document.getElementById('pat-sync-icon');
            const syncIconEl = syncIcon ? syncIcon.querySelector('i') : null;
            if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
            fetch('<?= BASE_PATH ?>api/enotf/patient-sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enr: '<?= $enr ?>' })
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