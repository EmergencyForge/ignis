<?php
/**
 * View: enotf/protokoll/rettdaten/1.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Redirects;

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
    header("Location: " . EnotfUrl::protokoll($daten['enr']));
    exit();
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');
$defaultUrl = EnotfUrl::protokoll($daten['enr']);

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

// Adresse aus JSON dekodieren
$adressData = [];
if (!empty($daten['transp_adresse'])) {
    $decoded = json_decode($daten['transp_adresse'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $adressData = $decoded;
    }
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
    <style>
        .poi-autocomplete-wrapper {
            position: relative;
        }

        .poi-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: #444;
            border: 1px solid #555;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .poi-item {
            padding: 8px 12px;
            cursor: pointer;
            color: white;
            border-bottom: 1px solid #555;
        }

        .poi-item:last-child {
            border-bottom: none;
        }

        .poi-item:hover {
            background-color: #555;
        }

        .poi-item-name {
            font-weight: bold;
        }

        .poi-item-address {
            font-size: 0.85em;
            opacity: 0.8;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="stammdaten" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="row">
                        <div class="col">
                            <div class="row my-1">
                                <div class="col">
                                    <h5>Objekt / POI / Einrichtung</h5>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <table class="w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="poi-autocomplete-wrapper">
                                                        <input type="text" name="transp_poi" id="transp_poi" class="w-100 form-control edivi__target" value="<?= htmlspecialchars($daten['transp_poi'] ?? '') ?>" autocomplete="off" data-ignore-autosave>
                                                        <div class="poi-dropdown" id="transp_poi-dropdown"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php $srAnfahrt = $daten['sonderrechte_anfahrt'] ?? null; ?>
                            <div class="row my-1">
                                <div class="col">
                                    <h5>Straße</h5>
                                </div>
                                <div class="col-3">
                                    <h5>HNR / Postal</h5>
                                </div>
                                <div class="col-2">
                                    <h5>SR Anfahrt <i id="icon-sonderrechte-anfahrt" class="fa-solid fa-circle-exclamation" style="color:#d91425;<?= !empty($srAnfahrt) ? 'display:none;' : '' ?>"></i></h5>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <table class="w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="text" name="transp_adresse_strasse" id="transp_adresse_strasse" class="w-100 form-control edivi__target" value="<?= htmlspecialchars($adressData['strasse'] ?? '') ?>" data-ignore-autosave>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-3">
                                    <table class="w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="text" name="transp_adresse_hnr" id="transp_adresse_hnr" class="w-100 form-control edivi__target" value="<?= htmlspecialchars($adressData['hnr'] ?? '') ?>" data-ignore-autosave>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-2">
                                    <button type="button" id="btn-sonderrechte-anfahrt" class="w-100 form-control edivi__target" style="cursor:pointer;text-align:center;background-color:#333333;border:1px solid #595959;border-radius:0;color:#fff;font-size:1.2rem;padding:0.2rem;"><?php
                                        if ($srAnfahrt === 'ja') echo 'ja';
                                        elseif ($srAnfahrt === 'nein') echo 'nein';
                                        else echo '—';
                                    ?></button>
                                </div>
                            </div>
                            <div class="row my-1">
                                <div class="col">
                                    <h5>Ort</h5>
                                </div>
                                <div class="col">
                                    <h5>Ortsteil</h5>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <table class="w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="text" name="transp_adresse_ort" id="transp_adresse_ort" class="w-100 form-control edivi__target" value="<?= htmlspecialchars($adressData['ort'] ?? '') ?>" data-ignore-autosave>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col">
                                    <table class="w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="text" name="transp_adresse_ortsteil" id="transp_adresse_ortsteil" class="w-100 form-control edivi__target" value="<?= htmlspecialchars($adressData['ortsteil'] ?? '') ?>" data-ignore-autosave>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="edivi__freigabe-buttons">
                        <div class="row">
                            <div class="col">
                                <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>">zurück</a>
                            </div>
                            <div class="col">
                                <a href="#" id="save-address-btn">speichern</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
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
        // POI Autocomplete System
        let poiData = [];
        let selectedPoiId = null;

        // POI-Daten laden
        function loadPOIs(searchTerm = '') {
            const url = '<?= BASE_PATH ?>api/enotf/poi/poi-search.php' + (searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('POI Load Error:', data.error);
                        return;
                    }
                    poiData = data;
                    populatePOIDropdown(searchTerm);
                })
                .catch(error => {
                    console.error('POI Fetch Error:', error);
                });
        }

        // POI Dropdown befüllen
        function populatePOIDropdown(filterValue = '') {
            const input = document.getElementById('transp_poi');
            const dropdown = document.getElementById('transp_poi-dropdown');

            if (!input || !dropdown) return;

            dropdown.innerHTML = '';

            const filteredPOIs = poiData.filter(poi =>
                poi.name.toLowerCase().includes(filterValue.toLowerCase()) ||
                poi.ort.toLowerCase().includes(filterValue.toLowerCase())
            );

            if (filteredPOIs.length === 0) {
                dropdown.style.display = 'none';
                return false;
            }

            filteredPOIs.forEach(poi => {
                const item = document.createElement('div');
                item.className = 'poi-item';

                // Adresse zusammenbauen
                let addressParts = [];
                if (poi.strasse) addressParts.push(poi.strasse);
                if (poi.hnr) addressParts.push(poi.hnr);
                if (poi.ort) addressParts.push(poi.ort);
                if (poi.ortsteil) addressParts.push('(' + poi.ortsteil + ')');

                const addressStr = addressParts.join(' ');

                item.innerHTML = `
                <div class="poi-item-name">${poi.name}</div>
                ${addressStr ? '<div class="poi-item-address">' + addressStr + '</div>' : ''}
            `;

                item.addEventListener('click', function() {
                    selectPOI(poi);
                    dropdown.style.display = 'none';
                });

                dropdown.appendChild(item);
            });

            return filteredPOIs.length > 0;
        }

        // POI auswählen und Felder ausfüllen
        function selectPOI(poi) {
            selectedPoiId = poi.id;

            document.getElementById('transp_poi').value = poi.name;
            document.getElementById('transp_adresse_strasse').value = poi.strasse || '';
            document.getElementById('transp_adresse_hnr').value = poi.hnr || '';
            document.getElementById('transp_adresse_ort').value = poi.ort || '';
            document.getElementById('transp_adresse_ortsteil').value = poi.ortsteil || '';
        }

        // POI Input Event Handlers
        document.addEventListener('DOMContentLoaded', function() {
            const poiInput = document.getElementById('transp_poi');
            const poiDropdown = document.getElementById('transp_poi-dropdown');

            if (!poiInput || !poiDropdown) return;

            // Initial laden beim Focus
            poiInput.addEventListener('focus', function() {
                if (poiData.length === 0) {
                    loadPOIs();
                } else {
                    if (populatePOIDropdown(this.value)) {
                        poiDropdown.style.display = 'block';
                    }
                }
            });

            // Bei Eingabe filtern
            poiInput.addEventListener('input', function() {
                const value = this.value;

                if (value.length >= 2) {
                    loadPOIs(value);
                } else if (value.length === 0) {
                    loadPOIs();
                } else {
                    if (populatePOIDropdown(value)) {
                        poiDropdown.style.display = 'block';
                    } else {
                        poiDropdown.style.display = 'none';
                    }
                }

                // Bei manueller Eingabe: POI-ID zurücksetzen
                selectedPoiId = null;
            });

            // Dropdown schließen beim Klick außerhalb
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.poi-autocomplete-wrapper')) {
                    poiDropdown.style.display = 'none';
                }
            });
        });

        // Speichern-Button Handler
        document.getElementById('save-address-btn').addEventListener('click', function(e) {
            e.preventDefault();

            const enr = <?= json_encode($enr) ?>;

            // POI-Name und Adressfelder auslesen
            const poi = document.getElementById('transp_poi').value;
            const strasse = document.getElementById('transp_adresse_strasse').value;
            const hnr = document.getElementById('transp_adresse_hnr').value;
            const ort = document.getElementById('transp_adresse_ort').value;
            const ortsteil = document.getElementById('transp_adresse_ortsteil').value;

            // Adresse als JSON-Objekt vorbereiten
            const adressData = {
                strasse: strasse,
                hnr: hnr,
                ort: ort,
                ortsteil: ortsteil
            };

            // AJAX Request zum Speichern
            const formData = new FormData();
            formData.append('enr', enr);
            formData.append('transp_poi', poi);
            formData.append('transp_adresse', JSON.stringify(adressData));

            fetch('<?= BASE_PATH ?>api/enotf/poi/save-field.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Toast-Benachrichtigung anzeigen
                        showToast('Adresse gespeichert', 'success');

                        // Optional: Zurück zur Hauptseite nach kurzer Verzögerung
                        setTimeout(function() {
                            window.location.href = '<?= Redirects::getRedirectUrl($defaultUrl); ?>';
                        }, 1000);
                    } else {
                        showToast('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Save Error:', error);
                    showToast('Fehler beim Speichern der Adresse', 'error');
                });
        });

        // Toast Benachrichtigung
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white border-0';
            toast.classList.add(type === 'success' ? 'bg-success' : 'bg-danger');
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
    </script>
    <script>
        // Sonderrechte Anfahrt Tri-State Toggle: leer → nein → ja → leer
        (function() {
            var btn = document.getElementById('btn-sonderrechte-anfahrt');
            var icon = document.getElementById('icon-sonderrechte-anfahrt');
            var currentValue = <?= json_encode($daten['sonderrechte_anfahrt'] ?? null) ?>;

            btn.addEventListener('click', function() {
                if (currentValue === null || currentValue === '') {
                    currentValue = 'nein';
                } else if (currentValue === 'nein') {
                    currentValue = 'ja';
                } else {
                    currentValue = null;
                }
                updateButton();
                saveValue();
            });

            function updateButton() {
                if (currentValue === 'ja') {
                    btn.textContent = 'ja';
                    icon.style.display = 'none';
                } else if (currentValue === 'nein') {
                    btn.textContent = 'nein';
                    icon.style.display = 'none';
                } else {
                    btn.textContent = '\u2014';
                    icon.style.display = '';
                }
            }

            function saveValue() {
                var formData = new FormData();
                formData.append('enr', <?= json_encode($enr) ?>);
                formData.append('field', 'sonderrechte_anfahrt');
                formData.append('value', currentValue || '');
                fetch('<?= BASE_PATH ?>api/enotf/save-fields.php', { method: 'POST', body: formData });
            }
        })();
    </script>
    <script>
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