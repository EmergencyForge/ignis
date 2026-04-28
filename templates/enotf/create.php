<?php
/**
 * View: eNOTF Create (Protokoll-Typ wählen)
 *
 * @var string $pinEnabled
 * @var \PDO   $pdo
 */

use App\Helpers\EnotfUrl;

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="<?= BASE_PATH ?>assets/functions/enotf/enrbridge" id="enrForm">
        <input type="hidden" name="new" value="1" />
        <input type="hidden" name="action" value="openOrCreate" />
        <input type="hidden" name="prot_by" id="prot_by" value="" />
        <input type="hidden" name="force_create" id="force_create" value="0" />
        <div class="container-fluid" id="edivi__container">
            <div class="h-full">
                <div id="edivi__content">
                    <div class="hr my-6" style="color:transparent"></div>
                    <div class="mx-5">
                        <input type="text" class="ignis-input mb-3" name="enr" id="enr" placeholder="Einsatznummer" required />
                    </div>
                    <div class="mx-5 my-6">
                        <button class="edivi__nidabutton flex w-100 align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="rdprot" name="rdprot" onclick="setProtBy(0)"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NF</span> Notfallprotokoll</button>
                    </div>
                    <div class="mx-5 my-6">
                        <button class="edivi__nidabutton flex w-100 align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="naprot" name="naprot" onclick="setProtBy(1)"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NA</span> Notarztprotokoll</button>
                    </div>
                    <div class="mx-5 my-6 text-center">
                        <a href="overview" class="edivi__nidabutton-secondary inline-block w-100">zurück</a>
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
                    <p class="text-muted text-sm">Das neue Protokoll wird mit einer Nummerierung versehen (z.B. _1, _2, etc.)</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--primary" id="confirmCreate">Trotzdem erstellen</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setProtBy(value) {
            document.getElementById('prot_by').value = value;
        }

        function checkForConflict(enr, protBy) {
            return fetch('<?= BASE_PATH ?>api/enotf/check-conflict', {
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