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

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

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

<body data-bs-theme="dark" data-page="abschluss" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" data-requires="ebesonderheiten">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/2.php?enr=<?= $daten['enr'] ?>" data-requires="uebergabe_an">
                                <span>Nachforderung NA</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Übergabe</span>
                            </a>
                            <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient" class="mt-2" style="border-top: 1px solid rgba(255,255,255,0.1);">
                                <span><i class="fa-solid fa-cloud-arrow-up me-1"></i> An Leitstelle senden</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3_1.php?enr=<?= $daten['enr'] ?>">
                                <span>Ort</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3_2.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>An</span>
                            </a>
                            <a href="#" id="freigabeButton" data-enr="<?= $daten['enr'] ?>">
                                <span>Freigabe</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="uebergabe_an-1" name="uebergabe_an" value="1" <?php echo ($daten['uebergabe_an'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-1">Arzt</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-2" name="uebergabe_an" value="2" <?php echo ($daten['uebergabe_an'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-2">Pflegepersonal</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-3" name="uebergabe_an" value="3" <?php echo ($daten['uebergabe_an'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-3">Rettungssanitäter</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-4" name="uebergabe_an" value="4" <?php echo ($daten['uebergabe_an'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-4">Rettungsassistent</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-5" name="uebergabe_an" value="5" <?php echo ($daten['uebergabe_an'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-5">Notfallsanitäter</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-6" name="uebergabe_an" value="6" <?php echo ($daten['uebergabe_an'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-6">Polizei</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-7" name="uebergabe_an" value="7" <?php echo ($daten['uebergabe_an'] == 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-7">Angehörige</label>

                            <input type="radio" class="btn-check" id="uebergabe_an-99" name="uebergabe_an" value="99" <?php echo ($daten['uebergabe_an'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_an-99">Sonstige</label>
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

    <!-- Freigabe Modal -->
    <div class="modal fade" id="freigabeModal" tabindex="-1" aria-labelledby="freigabeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="freigabeModalLabel">Klinikcode-Freigabe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <p class="mb-4">Klinikcode für Protokoll #<?= $daten['enr'] ?></p>
                    <div id="codeDisplay" class="display-3 fw-bold text-primary mb-4" style="letter-spacing: 0.5rem;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Lädt...</span>
                        </div>
                    </div>
                    <p class="text-muted small">Code gültig für 1 Stunde</p>
                    <p class="text-muted small mt-3">Zugriff unter:<br>
                        <span class="text-white"><?= 'https://' . SYSTEM_URL ?>/enotf/schnittstelle/klinikcode.php</span>
                    </p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                    <button type="button" class="btn btn-primary" id="copyCodeButton" disabled>Code kopieren</button>
                </div>
            </div>
        </div>
    </div>

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
        $(document).ready(function() {
            // Freigabe Button Handler
            $('#freigabeButton').on('click', function(e) {
                e.preventDefault();
                const enr = $(this).data('enr');

                // Modal öffnen
                const modal = new bootstrap.Modal(document.getElementById('freigabeModal'));
                modal.show();

                // Code generieren
                $.ajax({
                    url: '<?= BASE_PATH ?>api/generate-klinikcode.php',
                    method: 'POST',
                    data: {
                        enr: enr
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#codeDisplay').text(response.code);
                            $('#copyCodeButton').prop('disabled', false);
                        } else {
                            $('#codeDisplay').html('<span class="text-danger fs-6">Fehler: ' + response.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#codeDisplay').html('<span class="text-danger fs-6">Fehler beim Generieren des Codes</span>');
                    }
                });
            });

            // Code kopieren
            $('#copyCodeButton').on('click', function() {
                const code = $('#codeDisplay').text();
                navigator.clipboard.writeText(code).then(function() {
                    const btn = $('#copyCodeButton');
                    const originalText = btn.text();
                    btn.text('✓ Kopiert!');
                    setTimeout(function() {
                        btn.text(originalText);
                    }, 2000);
                });
            });
        });

        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        modalCloseButton.addEventListener('click', function() {
            freigeberInput.value = '';
        });
    </script>
    <script>
        function sendPatientToDispatch(e) {
            e.preventDefault();
            const syncIcon = document.getElementById('pat-sync-icon');
            const syncIconEl = syncIcon ? syncIcon.querySelector('i') : null;
            if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
            fetch('<?= BASE_PATH ?>api/enotf-patient-sync.php', {
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