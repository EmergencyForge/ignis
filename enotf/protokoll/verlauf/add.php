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
use App\Helpers\BloodSugarHelper;

$daten = array();
$message = '';
$messageType = '';

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

// Prüfung ob freigegeben
if ($ist_freigegeben) {
    header("Location: index.php?enr=" . $_GET['enr']);
    exit();
}

$enr = $daten['enr'];

// Initialize BloodSugarHelper
$bzHelper = new BloodSugarHelper($pdo);
$bzUnit = $bzHelper->getCurrentUnit();

// Form-Verarbeitung - GEÄNDERT: Einzelne Werte separat speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vitals'])) {
    try {
        $zeitpunkt = $_POST['zeitpunkt'];
        $erstellt_von = $_SESSION['username'] ?? 'Unbekannt';
        $gespeicherte_werte = 0;

        // Array der möglichen Vitalparameter
        $vitalparameter = [
            'spo2' => ['name' => 'SpO₂', 'einheit' => '%'],
            'atemfreq' => ['name' => 'Atemfrequenz', 'einheit' => '/min'],
            'etco2' => ['name' => 'etCO₂', 'einheit' => 'mmHg'],
            'herzfreq' => ['name' => 'Herzfrequenz', 'einheit' => '/min'],
            'rrsys' => ['name' => 'RR systolisch', 'einheit' => 'mmHg'],
            'rrdias' => ['name' => 'RR diastolisch', 'einheit' => 'mmHg'],
            'bz' => ['name' => 'Blutzucker', 'einheit' => 'mg/dl'],
            'temp' => ['name' => 'Temperatur', 'einheit' => '°C']
        ];

        // Prepare Statement für Einzelwerte
        $query = "INSERT INTO intra_edivi_vitalparameter_einzelwerte (enr, zeitpunkt, parameter_name, parameter_wert, parameter_einheit, erstellt_von) 
                  VALUES (:enr, :zeitpunkt, :parameter_name, :parameter_wert, :parameter_einheit, :erstellt_von)";
        $stmt = $pdo->prepare($query);

        // Jeden Vitalparameter einzeln speichern (nur wenn Wert vorhanden)
        foreach ($vitalparameter as $param_key => $param_info) {
            if (!empty($_POST[$param_key])) {
                $wert = $_POST[$param_key];

                // Für Blutzucker: Wert von Anzeige-Einheit zu Speicher-Einheit (mg/dl) konvertieren
                if ($param_key === 'bz') {
                    $wert = $bzHelper->toStorageUnit($wert);
                }

                $result = $stmt->execute([
                    'enr' => $enr,
                    'zeitpunkt' => $zeitpunkt,
                    'parameter_name' => $param_info['name'],
                    'parameter_wert' => $wert,
                    'parameter_einheit' => $param_info['einheit'],
                    'erstellt_von' => $erstellt_von
                ]);

                if ($result) {
                    $gespeicherte_werte++;
                }
            }
        }

        // Bemerkung separat speichern (falls vorhanden)
        if (!empty($_POST['bemerkung'])) {
            $result = $stmt->execute([
                'enr' => $enr,
                'zeitpunkt' => $zeitpunkt,
                'parameter_name' => 'Bemerkung',
                'parameter_wert' => $_POST['bemerkung'],
                'parameter_einheit' => '',
                'erstellt_von' => $erstellt_von
            ]);

            if ($result) {
                $gespeicherte_werte++;
            }
        }

        if ($gespeicherte_werte > 0) {
            header("Location: index.php?enr=" . $enr);
            exit();
        } else {
            $message = 'Keine Werte zum Speichern gefunden oder Fehler beim Speichern.';
            $messageType = 'warning';
        }
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
$currentDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="verlauf" data-pin-enabled="<?= $pinEnabled ?>">
    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <!-- Erfolg/Fehler-Meldung -->
                <?php if (!empty($message)): ?>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="vitalsForm" method="post" action="">
                    <input type="hidden" name="save_vitals" value="1" />
                    <input type="hidden" name="zeitpunkt" id="zeitpunkt" value="<?= $currentDateTime ?>" required>
                    <div class="row">
                        <div class="col position-relative">
                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="SpO₂" data-after="%">
                                    <input type="number" name="spo2" id="spo2" class="form-control edivi__vitalparam keypad-input" min="0" max="100" placeholder="96">
                                </div>
                                <div class="col edivi__vitalparam-box" data-before="AF" data-after="/min">
                                    <input type="number" name="atemfreq" id="atemfreq" class="form-control edivi__vitalparam keypad-input" min="0" max="40" placeholder="16">
                                </div>
                                <div class="col edivi__vitalparam-box" data-before="etCO₂" data-after="mmHg">
                                    <input type="number" name="etco2" id="etco2" class="form-control edivi__vitalparam keypad-input" min="0" max="100" placeholder="35">
                                </div>
                            </div>
                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="HF" data-after="/min">
                                    <input type="number" name="herzfreq" id="herzfreq" class="form-control edivi__vitalparam keypad-input" min="0" max="300" placeholder="80">
                                </div>
                                <div class="col edivi__vitalparam-box" data-before="NIBP/RR" data-after="mmHg">
                                    <input type="number" name="rrsys" id="rrsys" class="form-control edivi__vitalparam-shared keypad-input" min="0" max="300" placeholder="120" style="border-right:0!important">
                                    <div class="edivi_vitalparam-spacer">/</div>
                                    <input type="number" name="rrdias" id="rrdias" class="form-control edivi__vitalparam-shared keypad-input" min="0" max="300" placeholder="80" style="border-left:0!important">
                                </div>
                            </div>
                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="BZ" data-after="<?= htmlspecialchars($bzUnit) ?>">
                                    <input type="number" name="bz" id="bz" class="form-control edivi__vitalparam keypad-input"
                                        min="0"
                                        max="<?= $bzUnit === 'mmol/l' ? 40 : 700 ?>"
                                        step="<?= $bzUnit === 'mmol/l' ? '0.1' : '1' ?>"
                                        placeholder="<?= $bzUnit === 'mmol/l' ? '5.0' : '90' ?>">
                                </div>
                                <div class="col edivi__vitalparam-box" data-before="Temperatur" data-after="°C">
                                    <input type="number" name="temp" id="temp" class="form-control edivi__vitalparam keypad-input" min="10" max="45" placeholder="36,5">
                                </div>
                            </div>
                            <div class="row edivi__vitalparam-mainbuttons">
                                <div class="col">
                                    <a href="index.php?enr=<?= $enr ?>">Abbrechen</a>
                                </div>
                                <div class="col" style="border-left: 2px solid #191919;">
                                    <button type="submit" form="vitalsForm">
                                        Speichern
                                    </button>
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
                                <!-- Zahlen-Tastenfeld -->
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

                                <!-- Untere Funktions-Buttons -->
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

    <script>
        // Keypad Funktionalität
        let keypadCurrentField = null;

        // Feld-Namen Mapping für Anzeige
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

        // Event Listener für Eingabefelder - nach dem DOM geladen ist
        document.addEventListener('DOMContentLoaded', function() {
            // Keypad Event Listener
            const keypadInputs = document.querySelectorAll('.keypad-input');
            keypadInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    selectField(this);
                });
                input.addEventListener('click', function() {
                    selectField(this);
                });
            });

            // Bestehende Validierung für Vitalparameter - funktioniert für alle Eingabearten
            const vitalInputs = document.querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared');
            vitalInputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateField(this);
                });
            });
        });

        // Feld-Validierung (für alle Eingabearten)
        function validateField(field) {
            const rawVal = (field.value ?? '').toString().trim().toLowerCase();

            // Alle Klassen zurücksetzen
            field.classList.remove('text-warning', 'text-danger', 'text-success', 'text-semiwarning');

            if (rawVal === 'ng' || rawVal === 'nm') {
                return;
            }

            const value = parseFloat(field.value.replace(',', '.'));
            const fieldName = field.name;

            if (isNaN(value) || field.value === '') {
                return; // Kein Wert oder ungültiger Wert
            }

            let isWarning = false,
                isSemiWarning = false,
                isDanger = false,
                isSuccess = false;

            // Feldspezifische Validierung
            switch (fieldName) {
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
                    if (value < 80 || value > 199) isDanger = true;
                    else if (value >= 80 && value < 90 || value > 169) isWarning = true;
                    else if (value >= 90 && value < 101 || value > 149) isSemiWarning = true;
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

            if (isDanger) {
                field.classList.add('text-danger');
            } else if (isSemiWarning) {
                field.classList.add('text-semiwarning');
            } else if (isWarning) {
                field.classList.add('text-warning');
            } else if (isSuccess) {
                field.classList.add('text-success');
            }
        }

        // Feld auswählen
        function selectField(field) {
            // Alle aktiven Markierungen entfernen
            document.querySelectorAll('.keypad-input').forEach(input => {
                input.classList.remove('active-field');
                // Zurück zu number type setzen
                if (input.dataset.originalType) {
                    input.type = input.dataset.originalType;
                    delete input.dataset.originalType;
                }
            });

            // Neues Feld markieren
            field.classList.add('active-field');
            keypadCurrentField = field;

            // Field type auf text setzen um Kommas zu erlauben
            if (field.type === 'number') {
                field.dataset.originalType = 'number';
                field.type = 'text';
            }

            // Cursor ans Ende setzen
            setTimeout(() => {
                const length = field.value.length;
                field.setSelectionRange(length, length);
                field.focus();
            }, 0);

            // Feld-Info aktualisieren
            updateFieldInfo();
        }

        // Feld-Info aktualisieren
        function updateFieldInfo() {
            const info = document.getElementById('fieldInfo');
            if (keypadCurrentField && info) {
                const fieldName = fieldNames[keypadCurrentField.id] || keypadCurrentField.id;
                info.textContent = `Aktives Feld: ${fieldName}`;
            } else if (info) {
                info.textContent = 'Feld auswählen für Eingabe';
            }
        }

        // Ziffer direkt hinzufügen - sofortige Übertragung
        function keypadAddDigit(digit) {
            if (!keypadCurrentField) {
                showAlert('Bitte wählen Sie zuerst ein Eingabefeld aus.', {
                    type: 'warning',
                    title: 'Eingabefeld auswählen'
                });
                return;
            }

            let currentValue = keypadCurrentField.value || '';

            // Komma-Behandlung
            if (digit === ',') {
                if (currentValue.includes(',')) {
                    return; // Nur ein Komma erlaubt
                }
                if (currentValue === '') {
                    currentValue = '0,';
                } else {
                    currentValue += ',';
                }
            } else {
                // Normale Ziffer hinzufügen
                if (currentValue.includes(',')) {
                    // Es gibt bereits ein Komma
                    const parts = currentValue.split(',');
                    const nachKomma = parts[1] || '';

                    if (nachKomma.length >= 2) {
                        return; // Maximal 2 Nachkommastellen
                    }

                    // Ziffer nach dem Komma hinzufügen
                    currentValue = parts[0] + ',' + nachKomma + digit;
                } else {
                    // Kein Komma vorhanden
                    if (currentValue.length >= 3) {
                        return; // Maximal 3 Stellen vor dem Komma
                    }

                    // Ziffer vor dem Komma hinzufügen
                    currentValue += digit;
                }
            }

            // Sofort ins Feld übertragen
            keypadUpdateFieldValue(currentValue);
        }

        // Rückgängig/Löschen - sofortige Übertragung
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

        // Aktuelles Feld komplett löschen
        function keypadClearField() {
            if (keypadCurrentField) {
                keypadUpdateFieldValue('');
            }
        }

        // Wert direkt ins Feld setzen
        function keypadUpdateFieldValue(value) {
            if (!keypadCurrentField) return;

            const fieldId = keypadCurrentField.id;

            if (String(value).toLowerCase() === 'ng' || String(value).toLowerCase() === 'nm') {
                keypadCurrentField.value = value;
                keypadCurrentField.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');
                keypadCurrentField.dispatchEvent(new Event('input'));
                return;
            }

            if (value === '') {
                keypadCurrentField.value = '';
                keypadCurrentField.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');
                keypadCurrentField.dispatchEvent(new Event('input'));
                return;
            }

            // Komma für Validierung in Punkt umwandeln, aber echten Wert mit Komma im Feld belassen
            const numericValue = parseFloat(value.replace(',', '.'));

            if (!isNaN(numericValue)) {
                // Feldspezifische Grundvalidierung (nur für unmögliche Werte)
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
                        isValid = numericValue >= 0 && numericValue <= 700;
                        break;
                    case 'temp':
                        isValid = numericValue >= 10 && numericValue <= 45;
                        break;
                }

                // Wert immer setzen, Validierung erfolgt über Event-Handler
                keypadCurrentField.value = value;

                // Event-Handler auslösen für detaillierte Validierung (Warning/Danger/Success)
                keypadCurrentField.dispatchEvent(new Event('input'));
            }
        }

        // Hilfsfunktionen für Kompatibilität mit bestehenden Includes
        function setCurrentTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            const dateTimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
            const zeitpunktElement = document.getElementById('zeitpunkt');
            if (zeitpunktElement) {
                zeitpunktElement.value = dateTimeString;
            }
        }
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
                    values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250],
                    min: 0,
                    max: 260
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