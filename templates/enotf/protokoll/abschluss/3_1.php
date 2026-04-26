<?php
/**
 * View: enotf/protokoll/abschluss/3_1.php
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

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

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

<body data-bs-theme="dark" data-page="abschluss" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="flex flex-wrap -mx-3 h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="flex-1 px-3" id="edivi__content" style="padding-left: 0">
                    <div class="flex flex-wrap -mx-3" style="margin-left: 0">
                        <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '1') ?>" data-requires="ebesonderheiten">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '2') ?>" data-requires="uebergabe_ort">
                                <span>Nachforderung NA</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3') ?>" class="active">
                                <span>Übergabe</span>
                            </a>
                            <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient">
                                <span>An Leitstelle senden</span>
                            </a>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3_1') ?>" class="active">
                                <span>Ort</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3_2') ?>">
                                <span>An</span>
                            </a>
                            <a href="#" id="freigabeButton" data-enr="<?= $daten['enr'] ?>">
                                <span>Freigabe</span>
                            </a>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="uebergabe_ort-1" name="uebergabe_ort" value="1" <?php echo ($daten['uebergabe_ort'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-1">Schockraum</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-2" name="uebergabe_ort" value="2" <?php echo ($daten['uebergabe_ort'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-2">Praxis</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-3" name="uebergabe_ort" value="3" <?php echo ($daten['uebergabe_ort'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-3">ZNA / INA</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-4" name="uebergabe_ort" value="4" <?php echo ($daten['uebergabe_ort'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-4">Stroke Unit</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-5" name="uebergabe_ort" value="5" <?php echo ($daten['uebergabe_ort'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-5">Intensivstation</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-6" name="uebergabe_ort" value="6" <?php echo ($daten['uebergabe_ort'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-6">OP direkt</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-7" name="uebergabe_ort" value="7" <?php echo ($daten['uebergabe_ort'] == 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-7">Hausarzt</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-8" name="uebergabe_ort" value="8" <?php echo ($daten['uebergabe_ort'] == 8 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-8">Fachambulanz</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-9" name="uebergabe_ort" value="9" <?php echo ($daten['uebergabe_ort'] == 9 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-9">Chest Pain Unit</label>
                        </div>
                        <div class="w-2/12 flex flex-col edivi__interactbutton px-3">
                            <input type="radio" class="btn-check" id="uebergabe_ort-10" name="uebergabe_ort" value="10" <?php echo ($daten['uebergabe_ort'] == 10 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-10">Herzkatheterlabor</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-11" name="uebergabe_ort" value="11" <?php echo ($daten['uebergabe_ort'] == 11 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-11">Allgemeinstation</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-12" name="uebergabe_ort" value="12" <?php echo ($daten['uebergabe_ort'] == 12 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-12">Einsatzstelle</label>

                            <input type="radio" class="btn-check" id="uebergabe_ort-99" name="uebergabe_ort" value="99" <?php echo ($daten['uebergabe_ort'] == 99 ? 'checked' : '') ?> autocomplete="off">
                            <label for="uebergabe_ort-99">Sonstige</label>
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

    <!-- Freigabe Modal -->
    <div class="modal fade" id="freigabeModal" tabindex="-1" aria-labelledby="freigabeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="freigabeModalLabel">Klinikcode-Freigabe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <p class="mb-4">Klinikcode für Protokoll #<?= $daten['enr'] ?></p>
                    <div id="codeDisplay" class="display-3 font-bold text-[#7ba3d4] mb-4" style="letter-spacing: 0.5rem;">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Lädt...</span>
                        </div>
                    </div>
                    <p class="text-[var(--text-dimmed,#818189)] text-sm">Code gültig für 1 Stunde</p>
                    <p class="text-[var(--text-dimmed,#818189)] text-sm mt-3">Zugriff unter:<br>
                        <span class="text-white"><?= 'https://' . SYSTEM_URL ?>/enotf/schnittstelle/klinikcode.php</span>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                    <button type="button" class="ignis-btn ignis-btn--primary" id="copyCodeButton" disabled>Code kopieren</button>
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
                    url: '<?= BASE_PATH ?>api/klinik/generate-code.php',
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
                            $('#codeDisplay').html('<span class="text-[#d46b6b] text-base">Fehler: ' + response.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#codeDisplay').html('<span class="text-[#d46b6b] text-base">Fehler beim Generieren des Codes</span>');
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