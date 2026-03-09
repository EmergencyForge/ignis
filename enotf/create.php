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
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if (!isset($_SESSION['fahrername']) || !isset($_SESSION['protfzg'])) {
    header("Location: " . BASE_PATH . "enotf/login.php");
    exit();
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="<?= BASE_PATH ?>assets/functions/enotf/enrbridge.php" id="enrForm">
        <input type="hidden" name="new" value="1" />
        <input type="hidden" name="action" value="openOrCreate" />
        <input type="hidden" name="prot_by" id="prot_by" value="" />
        <input type="hidden" name="force_create" id="force_create" value="0" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="hr my-5" style="color:transparent"></div>
                    <div class="row mx-5">
                        <div class="col">
                            <input type="text" class="form-control mb-3" name="enr" id="enr" placeholder="Einsatznummer" required />
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton-primary w-100" id="rdprot" name="rdprot" onclick="setProtBy(0)">Rettungsdienst-Protokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton-primary w-100" id="naprot" name="naprot" onclick="setProtBy(1)">Notarzt-Protokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col text-center">
                            <a href="overview.php" class="edivi__nidabutton-secondary w-100" style="display:inline-block">zurück</a>
                        </div>
                    </div>
                </div>
            </div>
    </form>

    <!-- Konflikt Modal -->
    <div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="conflictModalLabel">Protokoll bereits vorhanden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="conflictMessage"></p>
                    <p><strong>Möchten Sie trotzdem ein neues Protokoll für diese Einsatznummer erstellen?</strong></p>
                    <p class="text-muted small">Das neue Protokoll wird mit einer Nummerierung versehen (z.B. _1, _2, etc.)</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" id="confirmCreate">Trotzdem erstellen</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setProtBy(value) {
            document.getElementById('prot_by').value = value;
        }

        function checkForConflict(enr, protBy) {
            return fetch('<?= BASE_PATH ?>api/enotf/check-conflict.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'enr=' + encodeURIComponent(enr) + '&prot_by=' + encodeURIComponent(protBy)
                })
                .then(response => response.json());
        }

        document.getElementById('enr').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9_]/g, '');
        });

        document.getElementById('enrForm').addEventListener('submit', function(e) {
            const protBy = document.getElementById('prot_by').value;
            const enr = document.getElementById('enr').value;
            const forceCreate = document.getElementById('force_create').value;

            if (protBy !== '0' && protBy !== '1') {
                e.preventDefault();
                showAlert("Bitte wähle ein Protokoll aus (RD oder NA).", {
                    type: 'warning',
                    title: 'Protokollauswahl erforderlich'
                });
                return;
            }

            if (!enr) {
                e.preventDefault();
                showAlert("Bitte gib eine Einsatznummer ein.", {
                    type: 'warning',
                    title: 'Einsatznummer erforderlich'
                });
                return;
            }

            // Wenn force_create gesetzt ist, normale Weiterleitung
            if (forceCreate === '1') {
                return;
            }

            // Konfliktprüfung
            e.preventDefault();
            checkForConflict(enr, protBy)
                .then(result => {
                    if (result.conflict) {
                        // Konflikt gefunden - Modal anzeigen
                        document.getElementById('conflictMessage').textContent = result.message;
                        const modal = new bootstrap.Modal(document.getElementById('conflictModal'));
                        modal.show();
                    } else {
                        // Kein Konflikt - normal weiterleiten
                        document.getElementById('enrForm').submit();
                    }
                })
                .catch(error => {
                    console.error('Fehler bei der Konfliktprüfung:', error);
                    // Bei Fehler normal weiterleiten
                    document.getElementById('enrForm').submit();
                });
        });

        document.getElementById('confirmCreate').addEventListener('click', function() {
            document.getElementById('force_create').value = '1';
            document.getElementById('enrForm').submit();
        });

        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        if (modalCloseButton && freigeberInput) {
            modalCloseButton.addEventListener('click', function() {
                freigeberInput.value = '';
            });
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>