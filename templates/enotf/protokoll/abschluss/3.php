<?php
/**
 * View: enotf/protokoll/abschluss/3.php
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

$ebesonderheiten = [];
if (!empty($daten['ebesonderheiten'])) {
    $decoded = json_decode($daten['ebesonderheiten'], true);
    if (is_array($decoded)) {
        $ebesonderheiten = array_map('intval', $decoded);
    }
}

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
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="abschluss" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '1') ?>" data-requires="ebesonderheiten">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <?php if ($daten['prot_by'] != 1) : ?>
                                <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '2') ?>" data-requires="na_nachf">
                                    <span>Nachforderung NA</span>
                                </a>
                            <?php endif; ?>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3') ?>" class="active">
                                <span>Übergabe</span>
                            </a>
                            <a href="#" onclick="sendPatientToDispatch(event)" id="btn-send-patient">
                                <span>An Leitstelle senden</span>
                            </a>
                        </div>
                        <div class="w-2/12 d-flex flex-column edivi__interactbutton-more px-3">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3_1') ?>">
                                <span>Ort</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'abschluss', '3_2') ?>">
                                <span>An</span>
                            </a>
                            <a href="#" id="freigabeButton" data-enr="<?= $daten['enr'] ?>">
                                <span>Freigabe</span>
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
                    <div id="codeDisplay" class="display-3 fw-bold text-[#7ba3d4] mb-4" style="letter-spacing: 0.5rem;">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Lädt...</span>
                        </div>
                    </div>
                    <p class="text-[var(--text-dimmed,#818189)] text-sm">Code gültig für 1 Stunde</p>
                    <p class="text-[var(--text-dimmed,#818189)] text-sm mt-3">Zugriff unter:<br>
                        <span class="text-light"><?= 'https://' . SYSTEM_URL ?>/enotf/schnittstelle/klinikcode.php</span>
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
                    url: '<?= BASE_PATH ?>api/klinik/generate-code',
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
    </script>
    <script>
        function sendPatientToDispatch(e) {
            e.preventDefault();
            const syncIcon = document.getElementById('pat-sync-icon');
            const syncIconEl = syncIcon ? syncIcon.querySelector('i') : null;
            if (syncIconEl) syncIconEl.style.color = '#f0ad4e';
            fetch('<?= BASE_PATH ?>api/enotf/patient-sync', {
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