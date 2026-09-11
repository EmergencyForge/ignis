<?php

/**
 * View: Protokoll anlegen (eNOTF v2) — v1-Optik.
 *
 * Markup/Optik = plugins/enotf/templates/enotf/create.php: ENR-Feld,
 * NF-/NA-Kacheln, Konflikt-Modal (Bootstrap). Technik = v2: Submit an
 * POST /enotf-v2/create (CreateController::store — enrbridge-Semantik
 * serverseitig verbindlich), Konfliktprüfung vor dem Submit über den
 * v2-Endpoint /api/enotf-v2/check-conflict. force_create=1 nach der
 * Modal-Bestätigung erzeugt eine Suffix-ENR (_1, _2, …).
 *
 * @var array $crew
 */

use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$pinEnabled  = EnotfV2Policy::pinEnabled() ? 'true' : 'false';
$createError = (string) ($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $__v1Title = 'eNOTF';
    require __DIR__ . '/_v1head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="<?= $e(EnotfV2Url::page('create')) ?>" id="enrForm">
        <input type="hidden" name="prot_by" id="prot_by" value="" />
        <input type="hidden" name="force_create" id="force_create" value="0" />
        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>" />
        <div class="container-fluid" id="edivi__container">
            <div class="h-full">
                <div id="edivi__content">
                    <div class="hr my-6" style="color:transparent"></div>
                    <?php if ($createError === 'invalid_enr'): ?>
                        <div class="mx-5 mb-3" style="color:#d91425;">
                            <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i>
                            Ungültige Einsatznummer — erlaubt sind nur Ziffern und Unterstriche.
                        </div>
                    <?php endif; ?>
                    <div class="mx-5">
                        <input type="text" class="ignis-input mb-3" name="enr" id="enr" placeholder="Einsatznummer" autocomplete="off" required />
                    </div>
                    <div class="mx-5 my-6">
                        <button class="edivi__nidabutton flex w-100 align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="rdprot" name="rdprot" onclick="setProtBy(0)"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NF</span> Notfallprotokoll</button>
                    </div>
                    <div class="mx-5 my-6">
                        <button class="edivi__nidabutton flex w-100 align-items-center" style="border-top:3px solid #dc3545;padding:16px 20px;" id="naprot" name="naprot" onclick="setProtBy(1)"><span style="color:#dc3545;font-weight:bold;font-size:1.3rem;margin-right:12px;">NA</span> Notarztprotokoll</button>
                    </div>
                    <div class="mx-5 my-6 text-center">
                        <a href="<?= $e(EnotfV2Url::page('overview')) ?>" class="edivi__nidabutton-secondary inline-block w-100">zurück</a>
                    </div>
                </div>
            </div>
    </form>

    <!-- Unsichtbarer Data-API-Trigger: vendor-enotf.js (Bootstrap-ESM)
         exportiert kein window.bootstrap — das Modal öffnet stattdessen
         zuverlässig über den Data-API-Klick -->
    <button type="button" id="conflictModalTrigger" data-bs-toggle="modal" data-bs-target="#conflictModal" hidden></button>

    <!-- Konflikt Modal (v1-Optik) -->
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

        // Konfliktprüfung über den v2-Endpoint (das v1-Pendant ist hart
        // auth-gated und liefert Crews ohne User-Login nur 401er)
        function checkForConflict(enr, protBy) {
            return fetch('<?= $e(EnotfV2Url::api('check-conflict')) ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
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
                        if (window.bootstrap && window.bootstrap.Modal) {
                            new bootstrap.Modal(document.getElementById('conflictModal')).show();
                        } else {
                            document.getElementById('conflictModalTrigger').click();
                        }
                    } else {
                        // Kein Konflikt - normal weiterleiten (der Server
                        // erzwingt die enrbridge-Semantik ohnehin)
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
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
