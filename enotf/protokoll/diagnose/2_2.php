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

use App\Helpers\EnotfUrl;
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
// Decode existing diagnose_weitere from JSON
$diagnose_weitere = [];
if (!empty($daten['diagnose_weitere'])) {
    $decoded = json_decode($daten['diagnose_weitere'], true);
    if (is_array($decoded)) {
        $diagnose_weitere = array_map('intval', $decoded);
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
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="diagnose" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '1') ?>" data-requires="diagnose_haupt">
                                <span>Diagnose (führend)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2') ?>" class="active">
                                <span>Diagnose (weitere)</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '3') ?>">
                                <span>Diagnose Text</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_1') ?>">
                                <span>ZNS</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_2') ?>" class="active">
                                <span>Herz-Kreislauf</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_3') ?>">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_4') ?>">
                                <span>Abdomen</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_5') ?>">
                                <span>Psychiatrie</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_6') ?>">
                                <span>Stoffwechsel</span>
                            </a>


                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_9') ?>">
                                <span>Sonstige</span>
                            </a>
                            <a href="<?= EnotfUrl::protokoll($daten['enr'], 'diagnose', '2_10') ?>">
                                <span>Trauma</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-11" name="diagnose_weitere[]" value="11" <?php echo (in_array(11, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-11">ACS / NSTEMI</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-12" name="diagnose_weitere[]" value="12" <?php echo (in_array(12, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-12">ACS / STEMI</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-13" name="diagnose_weitere[]" value="13" <?php echo (in_array(13, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-13">Kardiogener Schock</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-14" name="diagnose_weitere[]" value="14" <?php echo (in_array(14, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-14">tachykarde Arrhythmie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-15" name="diagnose_weitere[]" value="15" <?php echo (in_array(15, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-15">bradykarde Arrhythmie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-16" name="diagnose_weitere[]" value="16" <?php echo (in_array(16, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-16">Schrittmacher-/ICD Fehlfunktion</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-17" name="diagnose_weitere[]" value="17" <?php echo (in_array(17, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-17">Lungenembolie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-18" name="diagnose_weitere[]" value="18" <?php echo (in_array(18, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-18">Lungenödem</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-19" name="diagnose_weitere[]" value="19" <?php echo (in_array(19, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-19">hypertensiver Notfall</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-20" name="diagnose_weitere[]" value="20" <?php echo (in_array(20, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-20">Aortenaneurysma</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="checkbox" class="btn-check" id="diagnose_weitere-21" name="diagnose_weitere[]" value="21" <?php echo (in_array(21, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-21">Hypotonie</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-22" name="diagnose_weitere[]" value="22" <?php echo (in_array(22, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-22">Synkope</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-23" name="diagnose_weitere[]" value="23" <?php echo (in_array(23, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-23">Thrombose / art. Verschluss</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-24" name="diagnose_weitere[]" value="24" <?php echo (in_array(24, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-24">Herz-Kreislauf-Stillstand</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-25" name="diagnose_weitere[]" value="25" <?php echo (in_array(25, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-25">Schock unklarer Genese</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-26" name="diagnose_weitere[]" value="26" <?php echo (in_array(26, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-26">unklarer Thoraxschmerz</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-27" name="diagnose_weitere[]" value="27" <?php echo (in_array(27, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-27">orthostatische Fehlregulation</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-28" name="diagnose_weitere[]" value="28" <?php echo (in_array(28, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-28">hypertensive Krise / Entgleisung</label>

                            <input type="checkbox" class="btn-check" id="diagnose_weitere-29" name="diagnose_weitere[]" value="29" <?php echo (in_array(29, $diagnose_weitere) ? 'checked' : '') ?> autocomplete="off">
                            <label for="diagnose_weitere-29">sonstige Erkrankung Herz-Kreislauf</label>
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
    <script>
        $(document).ready(function() {
            <?php if (!$ist_freigegeben) : ?>
                // Entferne alte Event-Handler
                $('input[name="diagnose_weitere[]"]').off('change');

                // Definiere Kategorie-Bereiche (IDs die zu dieser Kategorie gehören)
                const categoryRanges = {
                    'zns': [1, 2, 3, 4, 5, 6, 9],
                    'herz': [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29],
                    'atemwege': [31, 32, 33, 34, 35, 36, 37, 38, 39, 49],
                    'abdomen': [51, 52, 53, 54, 55, 56, 59],
                    'psychiatrie': [61, 62, 63, 64, 65, 66, 67, 69],
                    'stoffwechsel': [71, 72, 73, 74, 75, 79],
                    'sonstige': [81, 82, 83, 84, 85, 86, 87, 88, 89, 91, 92, 93, 94, 99],
                    'trauma': [101, 102, 103, 104, 111, 112, 113, 114, 121, 122, 123, 124,
                        131, 132, 133, 134, 141, 142, 143, 144, 151, 152, 153,
                        161, 162, 163, 171, 172, 173, 181, 182, 183, 191, 192, 193,
                        201, 202, 203, 204, 205, 206, 209
                    ]
                };

                // Bestimme aktuelle Kategorie anhand der sichtbaren Checkboxen
                let currentCategoryRange = [];
                const firstCheckbox = $('input[name="diagnose_weitere[]"]').first();
                if (firstCheckbox.length > 0) {
                    const firstValue = parseInt(firstCheckbox.val());
                    for (const [cat, range] of Object.entries(categoryRanges)) {
                        if (range.includes(firstValue)) {
                            currentCategoryRange = range;
                            console.log('Aktuelle Kategorie:', cat, 'Range:', range);
                            break;
                        }
                    }
                }

                // Lade alle bestehenden Werte beim Start
                let allExistingValues = <?= json_encode($diagnose_weitere) ?>;
                console.log('Alle bestehenden Werte beim Laden:', allExistingValues);

                let saveTimer = null;

                $('input[name="diagnose_weitere[]"]').on('change', function() {
                    if (saveTimer) clearTimeout(saveTimer);

                    saveTimer = setTimeout(function() {
                        const urlParams = new URLSearchParams(window.location.search);
                        const enr = urlParams.get('enr');

                        if (!enr) {
                            console.error('ENR nicht gefunden');
                            return;
                        }

                        // Sammle nur die Werte der AKTUELLEN Seite
                        const currentPageValues = [];
                        $('input[name="diagnose_weitere[]"]:checked').each(function() {
                            currentPageValues.push(parseInt($(this).val()));
                        });

                        console.log('Aktuelle Seite - Ausgewählte Werte:', currentPageValues);

                        // Filtere bestehende Werte: Behalte nur die aus ANDEREN Kategorien
                        const otherCategoryValues = allExistingValues.filter(val => {
                            return !currentCategoryRange.includes(val);
                        });

                        console.log('Werte aus anderen Kategorien (behalten):', otherCategoryValues);

                        // Kombiniere: Andere Kategorien + aktuelle Seite
                        const finalValues = [...otherCategoryValues, ...currentPageValues].sort((a, b) => a - b);
                        const jsonValue = JSON.stringify(finalValues);

                        console.log('Final zu speichernde Werte:', finalValues);
                        console.log('Sending to server - ENR:', enr, 'Value:', jsonValue);

                        // Speichern
                        $.ajax({
                            url: '/api/enotf/save-fields.php',
                            method: 'POST',
                            data: {
                                enr: enr,
                                field: 'diagnose_weitere',
                                value: jsonValue
                            },
                            dataType: 'text',
                            success: function(response) {
                                if (response.trim().toLowerCase().startsWith('<!doctype') ||
                                    response.trim().toLowerCase().startsWith('<html')) {
                                    console.error('Fehler: Server hat HTML zurückgegeben');
                                    if (typeof showToast === 'function') {
                                        showToast('Fehler beim Speichern der Diagnosen', 'error');
                                    }
                                } else {
                                    console.log('✓ Erfolgreich gespeichert:', response);

                                    // Aktualisiere die lokale Kopie
                                    allExistingValues = finalValues;

                                    if (typeof showToast === 'function') {
                                        const count = currentPageValues.length;
                                        const totalCount = finalValues.length;
                                        const message = count === 0 ?
                                            `Diagnosen dieser Kategorie zurückgesetzt (${totalCount} gesamt)` :
                                            `${count} ${count === 1 ? 'Diagnose' : 'Diagnosen'} dieser Kategorie (${totalCount} gesamt)`;
                                        showToast(message, 'success');
                                    }

                                    // Update __dynamicDaten
                                    if (typeof window.__dynamicDaten !== 'undefined') {
                                        window.__dynamicDaten['diagnose_weitere'] = jsonValue;
                                    }
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('✗ AJAX Fehler:', error);
                                console.error('  Status:', xhr.status);
                                console.error('  Response:', xhr.responseText.substring(0, 500));
                                if (typeof showToast === 'function') {
                                    showToast('Fehler beim Speichern der Diagnosen', 'error');
                                }
                            }
                        });
                    }, 300);
                });
            <?php endif; ?>
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