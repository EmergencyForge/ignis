<?php
/**
 * View: enotf/protokoll/anamnese/1.php
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

<body data-bs-theme="dark" data-page="anamnese" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <div class="col d-flex flex-column" id="edivi__content">
                    <div class="row" style="flex-grow: 1;">
                        <div class="w-10/12 edivi__box py-1 px-3" style="margin: 10px">
                            <textarea name="anmerkungen" id="anmerkungen" class="w-100 form-control" style="resize: none; height: 100%; border-radius: 0;" rows="12" data-ignore-autosave><?= $daten['anmerkungen'] ?></textarea>
                        </div>
                        <?php if (!$ist_freigegeben) : ?>
                            <div class="col">
                                <div class="flex justify-center align-items-center" style="margin: 10px 0; height: 80px;">
                                    <button type="button" id="save-anamnese-btn" class="ignis-btn ignis-btn--success px-4 w-100 h-full" style="font-size:1.4rem">OK</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="anmerkungen-line-warning" style="display: none; margin: 0 10px; background: rgba(217, 20, 37, 0.85); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; flex-shrink: 0;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Der Text ist l&auml;nger als 22 Zeilen &ndash; ggf. wird nicht der komplette Text im Protokoll-Ausdruck sichtbar sein.
                    </div>
                    <?php if (!$ist_freigegeben) : ?>
                        <div class="flex" style="flex-shrink: 0;">
                            <div class="d-flex flex-column edivi__interactbutton" id="textblock-main" style="flex: 0 0 auto; min-width: 220px;">
                                <!-- Vorerkrankungen -->
                                <a href="javascript:void(0)" class="anamnese-textblock-btn has-submenu" data-key="vorerkrankungen" data-text="VORERKRANKUNGEN:" data-newline="2"><span>Vorerkrankungen</span></a>
                                <!-- Medikation -->
                                <a href="javascript:void(0)" class="anamnese-textblock-btn has-submenu" data-key="medikation" data-text="MEDIKATION:" data-newline="2"><span>Medikation</span></a>
                                <!-- Allergien -->
                                <a href="javascript:void(0)" class="anamnese-textblock-btn has-submenu" data-key="allergien" data-text="ALLERGIEN:" data-newline="2"><span>Allergien</span></a>
                                <!-- Drogen / Abusus -->
                                <a href="javascript:void(0)" class="anamnese-textblock-btn has-submenu" data-key="drogen" data-text="DROGEN / ABUSUS:" data-newline="2"><span>Drogen / Abusus</span></a>
                            </div>
                            <!-- Vorerkrankungen -->
                            <div class="flex-col edivi__interactbutton textblock-submenu" id="textblock-sub-vorerkrankungen" style="display: none; flex: 0 0 auto; min-width: 220px;">
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Beim Pat. sind keine Vorerkrankungen bekannt."><span>keine bekannt</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Es konnten keine Vorerkrankungen ermittelt werden."><span>nicht ermittelbar</span></a>
                            </div>
                            <!-- Medikation -->
                            <div class="flex-col edivi__interactbutton textblock-submenu" id="textblock-sub-medikation" style="display: none; flex: 0 0 auto; min-width: 220px;">
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Beim Pat. ist keine Vormedikation bekannt."><span>keine Vormedikation</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Es konnte keine Vormedikation ermittelt werden."><span>nicht ermittelbar</span></a>
                            </div>
                            <!-- Allergien -->
                            <div class="flex-col edivi__interactbutton textblock-submenu" id="textblock-sub-allergien" style="display: none; flex: 0 0 auto; min-width: 220px;">
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Beim Pat. sind keine Allergien bekannt."><span>keine Allergien</span></a>
                            </div>
                            <!-- Drogen / Abusus -->
                            <div class="flex-col edivi__interactbutton textblock-submenu" id="textblock-sub-drogen" style="display: none; flex: 0 0 auto; min-width: 220px;">
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Bekannter Nikotinabusus"><span>Nikotin</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Bekannter Alkoholabusus"><span>Alkohol</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Bekannter Opiatabusus"><span>Opiate</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Bekannter Benzodiazepinabusus"><span>Benzodiazepine</span></a>
                                <a href="javascript:void(0)" class="anamnese-subblock-btn" data-text="Beim Pat. ist Alkoholgeruch wahrnehmbar."><span>Foetor alcoholicus</span></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
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
        document.addEventListener('DOMContentLoaded', function() {
            const enr = <?= json_encode($enr) ?>;
            const textarea = document.getElementById('anmerkungen');
            const lineWarning = document.getElementById('anmerkungen-line-warning');

            // Zeilenanzahl prüfen und Warnung anzeigen
            function checkLineCount() {
                if (!textarea || !lineWarning) return;
                const lines = textarea.value.split('\n').length;
                lineWarning.style.display = lines > 22 ? 'block' : 'none';
            }

            checkLineCount();
            textarea.addEventListener('input', checkLineCount);

            // Textblock-Buttons: Text einfügen + Sub-Menü anzeigen
            var activeSubmenu = null;

            // newlineMode: 0 = kein Umbruch, 1 = \n davor, 2 = \n davor + \n danach, 3 = \n\n davor + \n danach
            function insertTextAtCursor(text, newlineMode) {
                const cursorPos = textarea.selectionStart;
                const before = textarea.value.substring(0, cursorPos);
                const after = textarea.value.substring(cursorPos);
                var prefix = '';
                if (newlineMode >= 1 && before.length > 0) {
                    prefix = '\n';
                }
                if (newlineMode >= 3 && before.length > 0) {
                    prefix = '\n\n';
                }
                const suffix = newlineMode >= 2 ? '\n' : '';
                const insertText = prefix + text + suffix;
                textarea.value = before + insertText + after;
                textarea.selectionStart = textarea.selectionEnd = cursorPos + insertText.length;
                textarea.focus();
                textarea.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                checkLineCount();
            }

            document.querySelectorAll('.anamnese-textblock-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var key = this.getAttribute('data-key');
                    var text = this.getAttribute('data-text');
                    var addNewline = parseInt(this.getAttribute('data-newline') || '0', 10);
                    var self = this;

                    // Alle Sub-Menüs ausblenden
                    document.querySelectorAll('.textblock-submenu').forEach(function(sub) {
                        sub.style.display = 'none';
                    });

                    // Alle Hauptbuttons deaktivieren
                    document.querySelectorAll('.anamnese-textblock-btn').forEach(function(b) {
                        b.classList.remove('active');
                    });

                    if (activeSubmenu === key) {
                        // Gleicher Button nochmal → nur deaktivieren, kein Text
                        activeSubmenu = null;
                    } else {
                        // Neuer Button → Text einfügen + Sub-Menü öffnen
                        insertTextAtCursor(text, addNewline);
                        var submenu = document.getElementById('textblock-sub-' + key);
                        if (submenu) {
                            submenu.style.display = 'flex';
                        }
                        self.classList.add('active');
                        activeSubmenu = key;
                    }
                });
            });

            // Sub-Menü Buttons: Text einfügen
            document.querySelectorAll('.anamnese-subblock-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    insertTextAtCursor(this.getAttribute('data-text'));
                });
            });

            // OK-Button: Speichern + Zurück zur Übersicht
            var saveBtn = document.getElementById('save-anamnese-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    $.ajax({
                        url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                        type: 'POST',
                        data: {
                            enr: enr,
                            field: 'anmerkungen',
                            value: textarea.value
                        }
                    }).done(function() {
                        window.location.href = '<?= BASE_PATH ?>enotf/protokoll/anamnese/index?enr=' + enr;
                    }).fail(function() {
                        showToast("Fehler beim Speichern", 'error');
                    });
                });
            }
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>