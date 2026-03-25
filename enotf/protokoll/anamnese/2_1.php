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

<body data-bs-theme="dark" data-page="anamnese" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/1.php?enr=<?= $daten['enr'] ?>">
                                <span>Anamnese</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2.php?enr=<?= $daten['enr'] ?>" data-requires="naca_initial" class="active">
                                <span>Symptome</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/3.php?enr=<?= $daten['enr'] ?>" data-requires="elokation">
                                <span>Einsatzort</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2_1.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Symptombeginn</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/anamnese/2_2.php?enr=<?= $daten['enr'] ?>" data-requires="naca_initial">
                                <span>NACA</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">Datum</label>
                            <input type="date" name="symptombeginn_datum" id="symptombeginn_datum"
                                class="edivi__interactbutton-input"
                                value="<?= !empty($daten['symptombeginn_datum']) ? date('Y-m-d', strtotime($daten['symptombeginn_datum'])) : '' ?>"
                                data-ignore-autosave>
                            <input type="checkbox" class="btn-check" id="symptombeginn_geschaetzt_1"
                                name="symptombeginn_geschaetzt" value="1"
                                <?= (!empty($daten['symptombeginn_geschaetzt']) ? 'checked' : '') ?>
                                autocomplete="off">
                            <label for="symptombeginn_geschaetzt_1">geschätzt</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">Zeit</label>
                            <input type="text" name="symptombeginn_zeit" id="symptombeginn_zeit"
                                class="edivi__interactbutton-input"
                                value="<?= $daten['symptombeginn_zeit'] ?? '' ?>"
                                placeholder="HH:MM" maxlength="5" inputmode="numeric"
                                pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]"
                                data-ignore-autosave>
                            <input type="checkbox" class="btn-check" id="symptombeginn_nf_1"
                                name="symptombeginn_nf" value="1"
                                <?= (!empty($daten['symptombeginn_nf']) ? 'checked' : '') ?>
                                autocomplete="off">
                            <label for="symptombeginn_nf_1">nicht feststellbar</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton justify-content-center">
                            <button type="button" id="save-symptombeginn-btn" class="btn btn-success w-100">
                                <i class="fa-solid fa-floppy-disk"></i> Speichern
                            </button>
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
        document.addEventListener('DOMContentLoaded', function() {
            const enr = <?= json_encode($enr) ?>;
            const _now = new Date();
            const heute = String(_now.getDate()).padStart(2, '0') + '.' + String(_now.getMonth() + 1).padStart(2, '0') + '.' + _now.getFullYear();
            const datumInput = document.getElementById('symptombeginn_datum');
            const zeitInput = document.getElementById('symptombeginn_zeit');
            const geschaetztCheckbox = document.getElementById('symptombeginn_geschaetzt_1');
            const nfCheckbox = document.getElementById('symptombeginn_nf_1');

            // Datum auf heute vorsetzen wenn leer
            if (!datumInput.value) {
                datumInput.value = heute;
            }

            // Zeit-Validierung (gleiche Logik wie force-24h-time.js)
            function formatTimeValue(value) {
                if (!value) return '';
                value = value.trim();
                let cleaned = value.replace(/[^0-9:]/g, '');
                let hours = '',
                    minutes = '';
                if (cleaned.includes(':')) {
                    const parts = cleaned.split(':');
                    hours = parts[0];
                    minutes = parts[1] || '00';
                } else {
                    if (cleaned.length === 4) {
                        hours = cleaned.substring(0, 2);
                        minutes = cleaned.substring(2, 4);
                    } else if (cleaned.length === 3) {
                        hours = '0' + cleaned.substring(0, 1);
                        minutes = cleaned.substring(1, 3);
                    } else if (cleaned.length === 2) {
                        hours = cleaned;
                        minutes = '00';
                    } else if (cleaned.length === 1) {
                        hours = '0' + cleaned;
                        minutes = '00';
                    } else return '';
                }
                hours = hours.padStart(2, '0').substring(0, 2);
                minutes = minutes.padStart(2, '0').substring(0, 2);
                if (parseInt(hours) > 23 || parseInt(minutes) > 59) return '';
                return hours + ':' + minutes;
            }

            zeitInput.addEventListener('input', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length > 4) value = value.substring(0, 4);
                if (value.length >= 3) {
                    this.value = value.substring(0, 2) + ':' + value.substring(2, 4);
                } else {
                    this.value = value;
                }
            });

            zeitInput.addEventListener('keydown', function(e) {
                if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
                if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x'].includes(e.key.toLowerCase())) return;
                if (e.key === ':') return;
                if (!/^[0-9]$/.test(e.key)) e.preventDefault();
            });

            zeitInput.addEventListener('blur', function() {
                if (this.value) {
                    const formatted = formatTimeValue(this.value);
                    this.value = formatted;
                }
            });

            // Zeit erst bei Klick/Focus auf aktuelle Uhrzeit vorfüllen
            zeitInput.addEventListener('focus', function() {
                if (!zeitInput.value) {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    zeitInput.value = hours + ':' + minutes;
                }
            });

            // geschätzt und nicht feststellbar gegenseitig exklusiv
            geschaetztCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    nfCheckbox.checked = false;
                }
            });

            nfCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    geschaetztCheckbox.checked = false;
                }
            });

            // Speichern-Button: alle 4 Felder gleichzeitig speichern
            document.getElementById('save-symptombeginn-btn').addEventListener('click', function(e) {
                e.preventDefault();

                const fields = [{
                        field: 'symptombeginn_datum',
                        value: datumInput.value
                    },
                    {
                        field: 'symptombeginn_zeit',
                        value: zeitInput.value
                    },
                ];

                let savePromises = fields.map(function(f) {
                    return $.ajax({
                        url: '<?= BASE_PATH ?>api/enotf/save-fields.php',
                        type: 'POST',
                        data: {
                            enr: enr,
                            field: f.field,
                            value: f.value
                        }
                    });
                });

                $.when.apply($, savePromises)
                    .done(function() {
                        showToast("Symptombeginn gespeichert.", 'success');
                        fields.forEach(function(f) {
                            window.__dynamicDaten[f.field] = String(f.value);
                        });
                    })
                    .fail(function() {
                        showToast("Fehler beim Speichern des Symptombeginns", 'error');
                    });
            });
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>