<?php
/**
 * View: enotf/protokoll/erstbefund/messwerte/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\BloodSugarHelper;

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

if ($ist_freigegeben) {
    header("Location: " . EnotfUrl::protokoll($_GET['enr'], 'erstbefund'));
    exit();
}

$enr = $daten['enr'];

// Initialize BloodSugarHelper
$bzHelper = new BloodSugarHelper($pdo);
$bzUnit = $bzHelper->getCurrentUnit();

// Konvertiere Blutzucker für Anzeige
if (!empty($daten['bz'])) {
    // Behalte 'ng' (nicht gemessen) unverändert
    if (strtolower(trim($daten['bz'])) !== 'ng') {
        $daten['bz'] = $bzHelper->formatValue($daten['bz'], false);
    }
}

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
$currentDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="verlauf" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <form name="form" id="vitalsForm" method="post" action="">
                    <div class="row">
                        <div class="col position-relative">
                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="SpO₂" data-after="%">
                                    <input type="text" name="spo2" id="spo2"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="100" placeholder="96" value="<?= $daten['spo2'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="AF" data-after="/min">
                                    <input type="text" name="atemfreq" id="atemfreq"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="60" placeholder="16" value="<?= $daten['atemfreq'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="etCO₂" data-after="mmHg">
                                    <input type="text" name="etco2" id="etco2"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="100" placeholder="35" value="<?= $daten['etco2'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="HF" data-after="/min">
                                    <input type="text" name="herzfreq" id="herzfreq"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="300" placeholder="80" value="<?= $daten['herzfreq'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="NIBP/RR" data-after="mmHg">
                                    <input type="text" name="rrsys" id="rrsys"
                                        class="form-control edivi__vitalparam-shared keypad-input"
                                        min="0" max="300" placeholder="120" style="border-right:0!important" value="<?= $daten['rrsys'] ?>" data-ignore-autosave>
                                    <div class="edivi_vitalparam-spacer">/</div>
                                    <input type="text" name="rrdias" id="rrdias"
                                        class="form-control edivi__vitalparam-shared keypad-input"
                                        min="0" max="300" placeholder="80" style="border-left:0!important" value="<?= $daten['rrdias'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="BZ" data-after="<?= htmlspecialchars($bzUnit) ?>">
                                    <input type="text" name="bz" id="bz"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0"
                                        max="<?= $bzUnit === 'mmol/l' ? 55 : 1000 ?>"
                                        step="<?= $bzUnit === 'mmol/l' ? '0.1' : '1' ?>"
                                        placeholder="<?= $bzUnit === 'mmol/l' ? '5.0' : '90' ?>"
                                        value="<?= $daten['bz'] ?>"
                                        data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="Temperatur" data-after="°C">
                                    <input type="text" name="temp" id="temp"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="10" max="45" step="0.1" placeholder="36,5" value="<?= $daten['temp'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row edivi__vitalparam-mainbuttons">
                                <div class="col"><a href="<?= EnotfUrl::protokoll($enr, 'erstbefund') ?>">Abbrechen</a></div>
                                <div class="col" style="border-left:2px solid #191919;">
                                    <button type="button" id="saveVitalsBtn">Speichern</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-5">
                            <!-- Range Strip -->
                            <div class="range-strip-wrapper">
                                <div class="range-strip-container">
                                    <div class="range-strip" id="rangeStrip"></div>
                                </div>
                            </div>
                            <!-- Keypad -->
                            <div class="keypad-container">
                                <div class="keypad-grid">
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('7')">7</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('8')">8</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('9')">9</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('4')">4</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('5')">5</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('6')">6</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('1')">1</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('2')">2</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('3')">3</button>
                                    <button type="button" class="keypad-btn wide" onclick="keypadAddDigit('0')">0</button>
                                    <button type="button" class="keypad-btn special" onclick="keypadAddDigit(',')">,</button>
                                </div>
                                <div class="function-grid">
                                    <button type="button" class="keypad-btn danger" onclick="keypadClearField()">
                                        Löschen
                                    </button>
                                    <button type="button" class="keypad-btn danger" onclick="keypadBackspace()"><i class="fa-solid fa-delete-left"></i></button>
                                    <button type="button" class="keypad-btn special" onclick="keypadSetNG()">nicht gemessen</button>
                                    <button type="button" class="keypad-btn special" onclick="keypadSetNM()">nicht messbar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../../assets/functions/enotf/field_checks.php';
    ?>
    <script>
        let keypadCurrentField = null;

        const fieldNames = {
            'spo2': 'SpO₂ (%)',
            'atemfreq': 'Atemfrequenz (/min)',
            'etco2': 'etCO₂ (mmHg)',
            'herzfreq': 'Herzfrequenz (/min)',
            'rrsys': 'RR systolisch (mmHg)',
            'rrdias': 'RR diastolisch (mmHg)',
            'bz': 'Blutzucker (<?= $bzUnit ?>)',
            'temp': 'Temperatur (°C)'
        };

        const requiredFields = ['spo2', 'atemfreq', 'herzfreq', 'rrsys', 'bz'];

        function isFieldEmpty(field) {
            const value = (field.value || '').trim();
            return value === '';
        }

        function updateRequiredClass(field) {
            const fieldId = field.id || field.name;

            if (!requiredFields.includes(fieldId)) {
                return;
            }

            if (isFieldEmpty(field)) {
                field.classList.add('edivi__vitalparam-required');
            } else {
                field.classList.remove('edivi__vitalparam-required');
            }
        }

        function initializeRequiredFields() {
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    updateRequiredClass(field);

                    field.addEventListener('input', function() {
                        updateRequiredClass(this);
                    });

                    field.addEventListener('blur', function() {
                        updateRequiredClass(this);
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeRequiredFields();

            const keypadInputs = document.querySelectorAll('.keypad-input');
            keypadInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    selectField(this);
                });
                input.addEventListener('click', function() {
                    selectField(this);
                });
            });

            const vitalInputs = document.querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared');
            vitalInputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateField(this);
                });
            });

            document
                .querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared')
                .forEach(function(el) {
                    validateField(el);
                });
        });

        function validateField(field) {
            const raw = ((field && field.value) ? String(field.value) : '').trim();
            const lower = raw.toLowerCase();

            field.classList.remove('text-warning', 'text-danger', 'text-success', 'text-info', 'text-semiwarning');

            updateRequiredClass(field);

            if (raw === '') return;

            const value = parseFloat(raw.replace(',', '.'));
            if (Number.isNaN(value)) return;

            const name = field.name;
            let isWarning = false,
                isSemiWarning = false,
                isDanger = false,
                isSuccess = false;

            switch (name) {
                case 'spo2':
                    if (value < 87) isDanger = true;
                    else if (value < 92) isWarning = true;
                    else if (value < 97 && value >= 92) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'atemfreq':
                    if (value < 5 || value > 25) isDanger = true;
                    else if (value === 5 || value === 6 || value > 20 && value < 26) isWarning = true;
                    else if (value > 6 && value < 9 || value > 15 && value < 21) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'etco2':
                    if (value < 6 || value > 55) isDanger = true;
                    else if (value < 36 || value > 45) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'rrsys':
                    if (value < 80 || value > 199) isDanger = true;
                    else if (value >= 80 && value < 90 || value > 169) isWarning = true;
                    else if (value >= 90 && value < 101 || value > 149) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'rrdias':
                    if (value <= 40 || value >= 121) isDanger = true;
                    else if (value >= 41 && value <= 50 || value >= 111 && value <= 120) isWarning = true;
                    else if (value >= 51 && value <= 60 || value >= 101 && value <= 110) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'herzfreq':
                    if (value < 41 || value > 160) isDanger = true;
                    else if (value < 51 || value > 130) isWarning = true;
                    else if (value < 61 || value > 100) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'bz':
                    <?php if ($bzUnit === 'mmol/l'): ?>
                        // mmol/l Bereiche
                        if (value < 2.2 || value > 13.9) isDanger = true;
                        else if ((value >= 2.2 && value < 2.8) || (value >= 10.0 && value < 13.9)) isWarning = true;
                        else if ((value >= 2.8 && value < 4.5) || (value >= 8.3 && value < 10.0)) isSemiWarning = true;
                        else isSuccess = true;
                    <?php else: ?>
                        // mg/dl Bereiche
                        if (value < 40 || value > 250) isDanger = true;
                        else if ((value >= 40 && value < 51) || (value >= 180 && value < 250)) isWarning = true;
                        else if ((value >= 51 && value < 81) || (value >= 150 && value < 180)) isSemiWarning = true;
                        else isSuccess = true;
                    <?php endif; ?>
                    break;
                case 'temp':
                    if (value <= 34 || value > 40) isDanger = true;
                    else if (value < 36.1 || value > 38) isSemiWarning = true;
                    else isSuccess = true;
                    break;
            }

            if (isDanger) field.classList.add('text-danger');
            else if (isSemiWarning) field.classList.add('text-semiwarning');
            else if (isWarning) field.classList.add('text-warning');
            else if (isSuccess) field.classList.add('text-success');
        }

        function selectField(field) {
            document.querySelectorAll('.keypad-input').forEach(input => {
                input.classList.remove('active-field');
                if (input.dataset.originalType) {
                    input.type = input.dataset.originalType;
                    delete input.dataset.originalType;
                }
            });

            field.classList.add('active-field');
            keypadCurrentField = field;

            if (field.type === 'number') {
                field.dataset.originalType = 'number';
                field.type = 'text';
            }

            setTimeout(() => {
                const length = field.value.length;
                field.setSelectionRange(length, length);
                field.focus();
            }, 0);

            updateFieldInfo();
        }

        function updateFieldInfo() {
            const info = document.getElementById('fieldInfo');
            if (keypadCurrentField && info) {
                const fieldName = fieldNames[keypadCurrentField.id] || keypadCurrentField.id;
                info.textContent = `Aktives Feld: ${fieldName}`;
            } else if (info) {
                info.textContent = 'Feld auswählen für Eingabe';
            }
        }

        function keypadSetNG() {
            if (!keypadCurrentField) {
                showAlert('Bitte wählen Sie zuerst ein Eingabefeld aus.', {
                    type: 'warning',
                    title: 'Eingabefeld auswählen'
                });
                return;
            }
            keypadUpdateFieldValue('ng');
        }

        function keypadSetNM() {
            if (!keypadCurrentField) {
                showAlert('Bitte wählen Sie zuerst ein Eingabefeld aus.', {
                    type: 'warning',
                    title: 'Eingabefeld auswählen'
                });
                return;
            }
            keypadUpdateFieldValue('nm');
        }

        function keypadAddDigit(digit) {
            if (!keypadCurrentField) {
                showAlert('Bitte wählen Sie zuerst ein Eingabefeld aus.', {
                    type: 'warning',
                    title: 'Eingabefeld auswählen'
                });
                return;
            }

            let currentValue = keypadCurrentField.value || '';

            if (digit === ',') {
                if (currentValue.includes(',')) {
                    return;
                }
                if (currentValue === '') {
                    currentValue = '0,';
                } else {
                    currentValue += ',';
                }
            } else {
                if (currentValue.includes(',')) {
                    const parts = currentValue.split(',');
                    const nachKomma = parts[1] || '';

                    if (nachKomma.length >= 2) {
                        return;
                    }

                    currentValue = parts[0] + ',' + nachKomma + digit;
                } else {
                    if (currentValue.length >= 3) {
                        return;
                    }

                    currentValue += digit;
                }
            }

            keypadUpdateFieldValue(currentValue);
        }

        function keypadBackspace() {
            if (!keypadCurrentField) {
                return;
            }

            let currentValue = keypadCurrentField.value || '';
            if (currentValue.length > 0) {
                currentValue = currentValue.slice(0, -1);
                keypadUpdateFieldValue(currentValue);
            }
        }

        function keypadClearField() {
            if (keypadCurrentField) {
                keypadUpdateFieldValue('');
            }
        }

        function keypadUpdateFieldValue(value) {
            if (!keypadCurrentField) return;

            const field = keypadCurrentField;
            const fieldId = field.id;

            if (String(value).toLowerCase() === 'ng' || String(value).toLowerCase() === 'nm') {
                field.value = value;
                field.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');
                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
                return;
            }

            if (value === '') {
                field.value = '';
                field.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');

                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
                return;
            }

            const numericValue = parseFloat(value.replace(',', '.'));
            if (!isNaN(numericValue)) {
                let isValid = true;
                switch (fieldId) {
                    case 'spo2':
                        isValid = numericValue >= 0 && numericValue <= 100;
                        break;
                    case 'atemfreq':
                        isValid = numericValue >= 0 && numericValue <= 40;
                        break;
                    case 'etco2':
                        isValid = numericValue >= 0 && numericValue <= 100;
                        break;
                    case 'herzfreq':
                        isValid = numericValue >= 0 && numericValue <= 300;
                        break;
                    case 'rrsys':
                    case 'rrdias':
                        isValid = numericValue >= 0 && numericValue <= 300;
                        break;
                    case 'bz':
                        isValid = numericValue >= 0 && numericValue <= <?= $bzUnit === 'mmol/l' ? 55 : 700 ?>;
                        break;
                    case 'temp':
                        isValid = numericValue >= 10 && numericValue <= 45;
                        break;
                }

                field.value = value;

                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
            }
        }

        function checkAllRequiredFields() {
            let allFilled = true;
            const emptyFields = [];

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && isFieldEmpty(field)) {
                    allFilled = false;
                    emptyFields.push(fieldNames[fieldId] || fieldId);
                }
            });

            return {
                allFilled: allFilled,
                emptyFields: emptyFields
            };
        }

        function validateBeforeSave() {
            const result = checkAllRequiredFields();

            // if (!result.allFilled) {
            //     const message = `Folgende Pflichtfelder sind noch nicht ausgefüllt:\n${result.emptyFields.join('\n')}`;

            //     if (!confirm(message + '\n\nTrotzdem speichern?')) {
            //         return false;
            //     }
            // }

            return true;
        }
    </script>
    <script>
        (function() {
            const enr = <?= json_encode($enr) ?>;
            const fields = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
            const endpoint = '<?= BASE_PATH ?>api/enotf/save-fields.php';

            async function postField(field, value) {
                const normalized = String(value).replace(',', '.').trim();
                if (normalized === '') return;

                const body = new URLSearchParams();
                body.set('enr', enr);
                body.set('field', field);
                body.set('value', normalized);

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body.toString()
                });
                const text = await res.text();
                if (!res.ok) throw new Error(text || ('Fehler bei ' + field));
                return text;
            }

            async function saveAll() {
                const fields = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
                const endpoint = '<?= BASE_PATH ?>api/enotf/save-fields.php';
                const enr = <?= json_encode($enr) ?>;

                const jobs = [];
                for (const f of fields) {
                    const el = document.getElementById(f);
                    if (!el) continue;

                    const raw = (el.value ?? '').toString().trim();
                    let toSend;
                    if (raw.toLowerCase() === 'ng' || raw.toLowerCase() === 'nm') {
                        toSend = raw;
                    } else if (raw === '') {
                        toSend = raw;
                    } else {
                        toSend = raw.replace(',', '.');
                    }

                    const body = new URLSearchParams();
                    body.set('enr', enr);
                    body.set('field', f);
                    body.set('value', toSend);

                    jobs.push(fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    }).then(async r => {
                        const t = await r.text();
                        if (!r.ok) throw new Error(t || ('Fehler bei ' + f));
                        return t;
                    }));
                }

                await Promise.all(jobs);
                (window.showToast ? window.showToast('Vitalparameter gespeichert.', 'success') : showAlert('Vitalparameter gespeichert.', {
                    type: 'success',
                    title: 'Erfolgreich gespeichert'
                }));
            }

            document.getElementById('saveVitalsBtn')?.addEventListener('click', function() {
                if (!validateBeforeSave()) {
                    return;
                }

                saveAll().catch(err => {
                    console.error(err);
                    (window.showToast ? window.showToast('Speichern fehlgeschlagen: ' + err.message, 'error') : showAlert('Speichern fehlgeschlagen: ' + err.message, {
                        type: 'error',
                        title: 'Fehler'
                    }));
                });
            });
        })();
    </script>
    <script>
        (function enforceNumericOrNG() {
            const inputs = document.querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared');

            function sanitizeNumeric(v) {
                v = (v || '').replace(/[^\d,\.]/g, '');

                const sepIndex = v.search(/[,\.]/);
                if (sepIndex >= 0) {
                    const before = v.slice(0, sepIndex).replace(/[,\.]/g, '');
                    const after = v.slice(sepIndex + 1).replace(/[,\.]/g, '');
                    return before.slice(0, 3) + v[sepIndex] + after.slice(0, 2);
                }
                return v.slice(0, 3);
            }

            inputs.forEach(el => {
                el.addEventListener('input', function() {
                    const raw = String(this.value || '').trim();
                    const lower = raw.toLowerCase();

                    if (raw === '') {
                        return;
                    }
                    if (lower === 'ng' || lower === 'nm') {
                        return;
                    }

                    const sanitized = sanitizeNumeric(raw);
                    if (sanitized !== raw) {
                        const pos = this.selectionStart;
                        this.value = sanitized;
                        try {
                            this.setSelectionRange(pos - (raw.length - sanitized.length),
                                pos - (raw.length - sanitized.length));
                        } catch {}
                    }
                });

                el.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey || e.altKey) return;
                    const k = e.key;

                    if (/^[a-zA-Z]$/.test(k)) {
                        const currentValue = String(this.value || '').trim().toLowerCase();
                        const inputLower = k.toLowerCase();

                        // Erlaube 'n' wenn Feld leer ist
                        if (currentValue === '' && inputLower === 'n') {
                            return;
                        }
                        // Erlaube 'g' wenn aktueller Wert 'n' ist (für 'ng')
                        if (currentValue === 'n' && inputLower === 'g') {
                            return;
                        }
                        // Erlaube 'm' wenn aktueller Wert 'n' ist (für 'nm')
                        if (currentValue === 'n' && inputLower === 'm') {
                            return;
                        }

                        // Alle anderen Buchstaben blockieren
                        e.preventDefault();
                        return;
                    }

                    if (!/^[0-9]$/.test(k) && k !== ',' && k !== '.' && k.length === 1) {
                        e.preventDefault();
                    }
                });
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';

            const colorRanges = {
                'spo2': [{
                        min: 97,
                        max: 100,
                        class: 'success'
                    },
                    {
                        min: 92,
                        max: 97,
                        class: 'semiwarning'
                    },
                    {
                        min: 87,
                        max: 92,
                        class: 'warning'
                    },
                    {
                        min: 70,
                        max: 87,
                        class: 'danger'
                    }
                ],
                'atemfreq': [{
                        min: 26,
                        max: 35,
                        class: 'danger'
                    },
                    {
                        min: 21,
                        max: 26,
                        class: 'warning'
                    },
                    {
                        min: 16,
                        max: 21,
                        class: 'semiwarning'
                    },
                    {
                        min: 9,
                        max: 16,
                        class: 'success'
                    },
                    {
                        min: 7,
                        max: 9,
                        class: 'semiwarning'
                    },
                    {
                        min: 5,
                        max: 7,
                        class: 'warning'
                    },
                    {
                        min: 0,
                        max: 5,
                        class: 'danger'
                    }
                ],
                'etco2': [{
                        min: 55,
                        max: 60,
                        class: 'danger'
                    },
                    {
                        min: 45,
                        max: 55,
                        class: 'semiwarning'
                    },
                    {
                        min: 36,
                        max: 45,
                        class: 'success'
                    },
                    {
                        min: 6,
                        max: 36,
                        class: 'semiwarning'
                    },
                    {
                        min: 0,
                        max: 6,
                        class: 'danger'
                    }
                ],
                'herzfreq': [{
                        min: 160,
                        max: 210,
                        class: 'danger'
                    },
                    {
                        min: 130,
                        max: 160,
                        class: 'warning'
                    },
                    {
                        min: 100,
                        max: 130,
                        class: 'semiwarning'
                    },
                    {
                        min: 61,
                        max: 100,
                        class: 'success'
                    },
                    {
                        min: 51,
                        max: 61,
                        class: 'semiwarning'
                    },
                    {
                        min: 41,
                        max: 51,
                        class: 'warning'
                    },
                    {
                        min: 20,
                        max: 41,
                        class: 'danger'
                    }
                ],
                'rrsys': [{
                        min: 199,
                        max: 260,
                        class: 'danger'
                    },
                    {
                        min: 169,
                        max: 199,
                        class: 'warning'
                    },
                    {
                        min: 149,
                        max: 169,
                        class: 'semiwarning'
                    },
                    {
                        min: 101,
                        max: 149,
                        class: 'success'
                    },
                    {
                        min: 90,
                        max: 101,
                        class: 'semiwarning'
                    },
                    {
                        min: 80,
                        max: 90,
                        class: 'warning'
                    },
                    {
                        min: 0,
                        max: 80,
                        class: 'danger'
                    }
                ],
                'rrdias': [{
                        min: 121,
                        max: 140,
                        class: 'danger'
                    },
                    {
                        min: 111,
                        max: 121,
                        class: 'warning'
                    },
                    {
                        min: 101,
                        max: 111,
                        class: 'semiwarning'
                    },
                    {
                        min: 61,
                        max: 101,
                        class: 'success'
                    },
                    {
                        min: 51,
                        max: 61,
                        class: 'semiwarning'
                    },
                    {
                        min: 41,
                        max: 51,
                        class: 'warning'
                    },
                    {
                        min: 0,
                        max: 41,
                        class: 'danger'
                    }
                ],
                'bz': <?= $bzUnit === 'mmol/l' ? '[{
                        min: 13.9,
                        max: 20,
                        class: "danger"
                    },
                    {
                        min: 10.0,
                        max: 13.9,
                        class: "warning"
                    },
                    {
                        min: 8.3,
                        max: 10.0,
                        class: "semiwarning"
                    },
                    {
                        min: 4.5,
                        max: 8.3,
                        class: "success"
                    },
                    {
                        min: 2.8,
                        max: 4.5,
                        class: "semiwarning"
                    },
                    {
                        min: 2.2,
                        max: 2.8,
                        class: "warning"
                    },
                    {
                        min: 0,
                        max: 2.2,
                        class: "danger"
                    }
                ]' : '[{
                        min: 250,
                        max: 360,
                        class: "danger"
                    },
                    {
                        min: 180,
                        max: 250,
                        class: "warning"
                    },
                    {
                        min: 150,
                        max: 180,
                        class: "semiwarning"
                    },
                    {
                        min: 81,
                        max: 150,
                        class: "success"
                    },
                    {
                        min: 51,
                        max: 81,
                        class: "semiwarning"
                    },
                    {
                        min: 40,
                        max: 51,
                        class: "warning"
                    },
                    {
                        min: 0,
                        max: 40,
                        class: "danger"
                    }
                ]' ?>,
                'temp': [{
                        min: 40,
                        max: 44,
                        class: 'danger'
                    },
                    {
                        min: 38,
                        max: 40,
                        class: 'semiwarning'
                    },
                    {
                        min: 36.1,
                        max: 38,
                        class: 'success'
                    },
                    {
                        min: 34,
                        max: 36.1,
                        class: 'semiwarning'
                    },
                    {
                        min: 30,
                        max: 34,
                        class: 'danger'
                    }
                ]
            };

            const rangeConfigs = {
                'spo2': {
                    label: 'SpO₂ (%)',
                    values: [72, 74, 76, 78, 80, 82, 84, 86, 88, 90, 92, 94, 96, 98],
                    min: 70,
                    max: 100
                },
                'atemfreq': {
                    label: 'Atemfrequenz (/min)',
                    values: [5, 10, 15, 20, 25, 30],
                    min: 0,
                    max: 35
                },
                'etco2': {
                    label: 'etCO₂ (mmHg)',
                    values: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
                    min: 0,
                    max: 60
                },
                'herzfreq': {
                    label: 'Herzfrequenz (/min)',
                    values: [40, 60, 80, 100, 120, 140, 160, 180, 200],
                    min: 20,
                    max: 210
                },
                'rrsys': {
                    label: 'RR systolisch (mmHg)',
                    values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250],
                    min: 0,
                    max: 260
                },
                'rrdias': {
                    label: 'RR diastolisch (mmHg)',
                    values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130],
                    min: 0,
                    max: 140
                },
                'bz': {
                    label: 'Blutzucker (<?= $bzUnit ?>)',
                    values: <?= $bzUnit === 'mmol/l'
                                ? '[1.1, 2.2, 3.3, 4.4, 5.6, 6.7, 7.8, 8.9, 10.0, 11.1, 12.2, 13.3, 14.4, 15.6, 16.7, 17.8, 18.9]'
                                : '[20, 40, 60, 80, 100, 120, 140, 160, 180, 200, 220, 240, 260, 280, 300, 320, 340]'
                            ?>,
                    min: 0,
                    max: <?= $bzUnit === 'mmol/l' ? 20 : 360 ?>
                },
                'temp': {
                    label: 'Temperatur (°C)',
                    values: [32, 34, 36, 38, 40, 42],
                    min: 30,
                    max: 44
                }
            };

            function getColorClass(value, fieldId) {
                const ranges = colorRanges[fieldId];
                if (!ranges) return 'success';

                for (const range of ranges) {
                    if (value >= range.min && value < range.max) {
                        return range.class;
                    }
                }
                return 'danger';
            }

            function generateColorSegments(fieldId, config) {
                const ranges = colorRanges[fieldId];
                if (!ranges) return [];

                const segments = [];
                const totalRange = config.max - config.min;

                ranges.forEach(range => {
                    const segmentHeight = ((range.max - range.min) / totalRange) * 100;

                    segments.push({
                        class: range.class,
                        height: segmentHeight,
                        min: range.min,
                        max: range.max
                    });
                });

                return segments;
            }

            function calculatePosition(value, config) {
                const range = config.max - config.min;
                const position = 100 - ((value - config.min) / range) * 100;
                return Math.max(0, Math.min(100, position));
            }

            function updateValueIndicator(fieldId, stripElement) {
                const field = document.getElementById(fieldId);
                if (!field || !rangeConfigs[fieldId]) return;

                const value = field.value.trim().toLowerCase();

                const existingIndicator = stripElement.querySelector('.value-indicator');
                if (existingIndicator) {
                    existingIndicator.remove();
                }

                if (!value || value === 'ng') return;

                const numValue = parseFloat(value.replace(',', '.'));
                if (isNaN(numValue)) return;

                const config = rangeConfigs[fieldId];
                const position = calculatePosition(numValue, config);

                const indicator = document.createElement('div');
                indicator.className = 'value-indicator';
                indicator.style.top = `${position}%`;
                indicator.setAttribute('data-value', value);

                stripElement.appendChild(indicator);
            }

            function renderRangeStrip(fieldId) {
                const stripElement = document.getElementById('rangeStrip');

                if (!stripElement) return;

                if (!fieldId || !rangeConfigs[fieldId]) {
                    stripElement.innerHTML = '';
                    return;
                }

                const config = rangeConfigs[fieldId];
                const segments = generateColorSegments(fieldId, config);

                let html = '';

                segments.forEach((segment, index) => {
                    html += `<div class="range-segment ${segment.class}" style="flex: ${segment.height}; position: relative;">`;

                    const segmentValues = config.values
                        .filter(val => {
                            if (index === segments.length - 1) {
                                return val >= segment.min && val <= segment.max;
                            }
                            return val >= segment.min && val < segment.max;
                        })
                        .sort((a, b) => b - a);

                    segmentValues.forEach(val => {
                        const valuePositionInRange = (val - config.min) / (config.max - config.min);
                        const positionFromTop = (1 - valuePositionInRange) * 100;
                        const segmentStart = (segment.max - config.min) / (config.max - config.min);
                        const segmentStartPercent = (1 - segmentStart) * 100;
                        const segmentEnd = (segment.min - config.min) / (config.max - config.min);
                        const segmentEndPercent = (1 - segmentEnd) * 100;
                        const positionInSegment = ((positionFromTop - segmentStartPercent) / (segmentEndPercent - segmentStartPercent)) * 100;

                        html += `<span class="range-value" style="position: absolute; top: ${positionInSegment}%; left: 50%; transform: translate(-50%, -50%);">${val}</span>`;
                    });

                    html += '</div>';
                });

                stripElement.innerHTML = html;

                updateValueIndicator(fieldId, stripElement);
            }

            const originalSelectField = window.selectField;
            window.selectField = function(field) {
                if (originalSelectField) {
                    originalSelectField(field);
                }
                renderRangeStrip(field.id);
            };

            document.addEventListener('DOMContentLoaded', function() {
                const keypadInputs = document.querySelectorAll('.keypad-input');

                keypadInputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        renderRangeStrip(this.id);
                    });

                    input.addEventListener('input', function() {
                        if (this.classList.contains('active-field')) {
                            const stripElement = document.getElementById('rangeStrip');
                            if (stripElement) {
                                updateValueIndicator(this.id, stripElement);
                            }
                        }
                    });
                });

                renderRangeStrip(null);
            });

            window.updateRangeStrip = renderRangeStrip;
        })();
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>