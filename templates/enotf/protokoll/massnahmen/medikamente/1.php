<?php
/**
 * View: enotf/protokoll/massnahmen/medikamente/1.php
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
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . rtrim(EnotfUrl::protokoll($enr), '/');
$defaultUrl = EnotfUrl::protokoll($daten['enr'], 'massnahmen', 'medikamente');

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i:s');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="massnahmen" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-full">
                <div class="col" id="edivi__content edivi__medikamente">
                    <div class="row mt-4 mx-4">
                        <div class="col">
                            <div class="w-100 p-3" style="background-color: #333333; min-height: 60vh; border-radius: 8px;" id="medis-list">
                                <div class="text-center text-[var(--text-dimmed,#818189)] p-4">
                                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2em;"></i>
                                    <div class="mt-2">Medikamente werden geladen...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <?php
                            // Load medications from database, sorted alphabetically by wirkstoff
                            $medStmt = $pdo->prepare("SELECT wirkstoff, herstellername, dosierungen FROM intra_edivi_medikamente WHERE active = 1 ORDER BY wirkstoff ASC");
                            $medStmt->execute();
                            $medikamente = $medStmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <div class="row">
                                <div class="col">
                                    <select class="form-select medikament-field-ignore" name="medis-select" id="medis-select" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true" data-custom-dropdown="true" data-search-threshold="10">
                                        <option value="" disabled hidden selected>Wirkstoff</option>
                                        <?php foreach ($medikamente as $med): ?>
                                            <?php
                                            $displayName = htmlspecialchars($med['wirkstoff']);
                                            if (!empty($med['herstellername'])) {
                                                $displayName = htmlspecialchars($med['herstellername']) . ' (' . htmlspecialchars($med['wirkstoff']) . ')';
                                            }
                                            ?>
                                            <option value="<?= htmlspecialchars($med['wirkstoff']) ?>" data-dosierungen="<?= htmlspecialchars($med['dosierungen'] ?? '') ?>"><?= $displayName ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <input type="time" name="medis-time" id="medis-time" class="form-control medikament-field-ignore" style="background-color: #333333; color: white;" step="1" value="<?= $currentTime ?>" data-ignore-autosave="true">
                                </div>
                                <div class="col">
                                    <div class="col">
                                        <select class="form-select medikament-field-ignore" name="medis-admission" id="medis-admission" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true" data-custom-dropdown="true">
                                            <option value="" disabled hidden selected>Applikationsart</option>
                                            <option value="i.v.">i.v.</option>
                                            <option value="i.o.">i.o.</option>
                                            <option value="i.m.">i.m.</option>
                                            <option value="s.c.">s.c.</option>
                                            <option value="s.l.">s.l.</option>
                                            <option value="p.o.">p.o.</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <div class="relative" id="dosierung-autocomplete-wrapper">
                                        <input class="form-control medikament-field-ignore" type="text" placeholder="Dosierung" name="medis-concentration" id="medis-concentration" autocomplete="off" style="background-color: #333333; color: white; --bs-secondary-color: #a2a2a2" data-ignore-autosave="true">
                                        <div id="dosierung-dropdown" class="dosierung-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background-color: #444; border: 1px solid #555; border-radius: 4px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"></div>
                                    </div>
                                </div>
                                <div class="col">
                                    <select class="form-select medikament-field-ignore" name="medis-unit" id="medis-unit" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true" data-custom-dropdown="true">
                                        <option value="" disabled hidden selected>Einheit</option>
                                        <option value="mcg">&micro;g</option>
                                        <option value="mg">mg</option>
                                        <option value="g">g</option>
                                        <option value="ml">ml</option>
                                        <option value="IE">IE</option>
                                    </select>
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
                                <a href="#" id="delete-btn">Löschen</a>
                            </div>
                            <div class="col">
                                <a href="#" id="save-btn">Speichern</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php
    include __DIR__ . '/../../../../../assets/functions/enotf/notify.php';
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
        function showMedikamentToast(message, type = 'success') {
            let toastContainer = document.getElementById('medikament-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'medikament-toast-container';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
                document.body.appendChild(toastContainer);
            }

            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };

            const bgColor = colors[type] || colors.success;

            const toast = document.createElement('div');
            toast.style.cssText = `
                border-left: 4px solid ${bgColor};
                background-color: #2c2c2c;
                color: #fff;
                padding: 12px 16px;
                margin-bottom: 10px;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                font-family: Arial, sans-serif;
                font-size: 14px;
                opacity: 0;
                transform: translateX(300px);
                transition: all 0.3s ease;
                max-width: 300px;
                word-wrap: break-word;
            `;
            toast.textContent = message;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(300px)';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const enr = new URLSearchParams(window.location.search).get('enr');

            loadMedikamente();

            const saveButton = document.getElementById('save-btn');
            if (saveButton) {
                saveButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    saveMedikament();
                });
            }

            const deleteButton = document.getElementById('delete-btn');
            if (deleteButton) {
                deleteButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    deleteSelectedMedikament();
                });
            }

            // Update dropdown when medication is selected
            const medisSelect = document.getElementById('medis-select');
            if (medisSelect) {
                medisSelect.addEventListener('change', function() {
                    updateDosierungDropdown(this);
                });
            }

            // Setup custom dropdown for dosage
            const dosierungInput = document.getElementById('medis-concentration');
            const dosierungDropdown = document.getElementById('dosierung-dropdown');

            if (dosierungInput && dosierungDropdown) {
                // Show dropdown on focus
                dosierungInput.addEventListener('focus', function() {
                    if (dosierungDropdown.children.length > 0) {
                        dosierungDropdown.style.display = 'block';
                    }
                });

                // Filter dropdown on input
                dosierungInput.addEventListener('input', function() {
                    const value = this.value.toLowerCase();
                    const items = dosierungDropdown.querySelectorAll('.dosierung-item');
                    let hasVisible = false;

                    items.forEach(item => {
                        if (item.textContent.toLowerCase().includes(value)) {
                            item.style.display = 'block';
                            hasVisible = true;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    dosierungDropdown.style.display = hasVisible ? 'block' : 'none';

                    // Also try to parse and set unit
                    parseDosierungAndSetUnit(this.value);
                });

                // Hide dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('#dosierung-autocomplete-wrapper')) {
                        dosierungDropdown.style.display = 'none';
                    }
                });
            }
        });

        function updateDosierungDropdown(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const dosierungen = selectedOption.dataset.dosierungen || '';
            const dropdown = document.getElementById('dosierung-dropdown');

            // Clear existing options
            dropdown.innerHTML = '';

            if (dosierungen) {
                // Split by comma and create styled options
                const dosierungValues = dosierungen.split(',').map(d => d.trim()).filter(d => d);
                dosierungValues.forEach(value => {
                    const item = document.createElement('div');
                    item.className = 'dosierung-item';
                    item.textContent = value;
                    item.style.cssText = 'padding: 8px 12px; cursor: pointer; color: white; border-bottom: 1px solid #555;';

                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#555';
                    });
                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'transparent';
                    });
                    item.addEventListener('click', function() {
                        // Parse and set unit first, which may modify the concentration value
                        const parsed = parseDosierungAndSetUnit(value);
                        // If parsing failed, just set the full value
                        if (!parsed) {
                            document.getElementById('medis-concentration').value = value;
                        }
                        dropdown.style.display = 'none';
                        // Clear dropdown content after selection
                        dropdown.innerHTML = '';
                    });

                    dropdown.appendChild(item);
                });

                // Remove border from last item
                if (dropdown.lastChild) {
                    dropdown.lastChild.style.borderBottom = 'none';
                }
            }
        }

        function parseDosierungAndSetUnit(value) {
            console.log('Parsing dosierung:', value);

            // Map of unit suffixes to select values (supports both mcg and µg)
            const unitMap = {
                'mcg': 'mcg',
                'μg': 'mcg',
                'µg': 'mcg',
                'ug': 'mcg',
                'mg': 'mg',
                'g': 'g',
                'ml': 'ml',
                'ie': 'IE'
            };

            // Try to match a predefined dosage pattern (number + optional space + unit)
            // More flexible regex to handle various formats
            const match = value.match(/^([0-9]+(?:[.,][0-9]+)?)\s*(mcg|μg|µg|ug|mg|g|ml|ie)$/i);

            console.log('Match result:', match);

            if (match) {
                const numericValue = match[1];
                const unit = match[2].toLowerCase();
                const unitSelectValue = unitMap[unit];

                console.log('Numeric:', numericValue, 'Unit:', unit, 'Select value:', unitSelectValue);

                if (unitSelectValue) {
                    // Set the numeric value in the input field
                    document.getElementById('medis-concentration').value = numericValue;

                    // Auto-select the unit in the original select element
                    const unitSelect = document.getElementById('medis-unit');
                    unitSelect.value = unitSelectValue;

                    // Trigger change event to update custom dropdown
                    const changeEvent = new Event('change', {
                        bubbles: true
                    });
                    unitSelect.dispatchEvent(changeEvent);

                    // Also refresh the custom dropdown if available
                    if (typeof eNOTFCustomDropdown !== 'undefined') {
                        setTimeout(() => {
                            eNOTFCustomDropdown.refresh(unitSelect);
                        }, 50);
                    }

                    console.log('Set unit select to:', unitSelectValue, 'Current value:', unitSelect.value);
                    return true;
                }
            }

            console.log('Failed to parse dosierung format');
            return false;
        }

        function saveMedikament() {
            const wirkstoff = document.getElementById('medis-select').value;
            const zeit = document.getElementById('medis-time').value;
            const applikation = document.getElementById('medis-admission').value;
            const dosierung = document.getElementById('medis-concentration').value;
            const einheit = document.getElementById('medis-unit').value;
            const enr = new URLSearchParams(window.location.search).get('enr');

            if (!wirkstoff || !zeit || !applikation || !dosierung || !einheit) {
                showMedikamentToast('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }

            const medikament = {
                wirkstoff: wirkstoff,
                zeit: zeit,
                applikation: applikation,
                dosierung: dosierung,
                einheit: einheit,
                timestamp: new Date().getTime()
            };

            const saveButton = document.getElementById('save-btn');
            const originalText = saveButton.textContent;
            saveButton.textContent = 'Speichert...';
            saveButton.style.pointerEvents = 'none';

            fetch('./save_medikament', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${encodeURIComponent(enr)}&action=add&medikament=${encodeURIComponent(JSON.stringify(medikament))}`
                })
                .then(response => {
                    saveButton.textContent = originalText;
                    saveButton.style.pointerEvents = '';

                    if (!response.ok) {
                        return response.text().then(errorText => {
                            throw new Error(`HTTP ${response.status}: ${errorText}`);
                        });
                    }
                    return response.text();
                })
                .then(data => {
                    if (data.includes('erfolgreich')) {
                        resetForm();
                        loadMedikamente();
                        showMedikamentToast('Medikament erfolgreich hinzugefügt.', 'success');
                        clearKeineMedikamenteState();
                    } else {
                        showMedikamentToast('Fehler beim Speichern: ' + data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMedikamentToast('Fehler beim Speichern: ' + error.message, 'error');
                });
        }

        function clearKeineMedikamenteState() {
            console.log('Medications added - "Keine Medikamente" state should be cleared on return to main page');
        }

        function loadMedikamente() {
            const enr = new URLSearchParams(window.location.search).get('enr');

            const listContainer = document.getElementById('medis-list');
            listContainer.innerHTML = '<div class="text-center text-[var(--text-dimmed,#818189)] p-4"><i class="fa-solid fa-spinner fa-spin" style="font-size: 2em;"></i><div class="mt-2">Medikamente werden geladen...</div></div>';

            fetch('./load_medikamente', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${encodeURIComponent(enr)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    displayMedikamente(data);
                })
                .catch(error => {
                    console.error('Error loading medikamente:', error);
                    listContainer.innerHTML = '<div class="text-center text-[#d46b6b] p-4"><i class="fa-solid fa-triangle-exclamation" style="font-size: 2em;"></i><div class="mt-2">Fehler beim Laden der Medikamente:<br>' + error.message + '</div></div>';
                });
        }

        function loadMedikamente() {
            const enr = new URLSearchParams(window.location.search).get('enr');

            fetch('./load_medikamente', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${enr}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    displayMedikamente(data);
                })
                .catch(error => {
                    console.error('Error loading medikamente:', error);
                    document.getElementById('medis-list').innerHTML = '<div class="text-center text-[#d46b6b] p-4">Fehler beim Laden der Medikamente: ' + error.message + '</div>';
                });
        }

        function displayMedikamente(medikamente) {
            const listContainer = document.getElementById('medis-list');
            listContainer.innerHTML = '';

            if (!medikamente || medikamente.length === 0) {
                listContainer.innerHTML = '<div class="text-center p-4"><i class="fa-solid fa-pills" style="font-size: 3em;"></i><div class="mt-2">Keine Medikamente eingetragen</div></div>';
                return;
            }

            medikamente.sort((a, b) => {
                return a.zeit.localeCompare(b.zeit);
            });

            medikamente.forEach((med, index) => {
                const medDiv = document.createElement('div');
                medDiv.className = 'medikament-item p-1 mb-1';
                medDiv.style.cssText = 'cursor: pointer; transition: all 0.2s;';
                medDiv.dataset.index = index;
                medDiv.dataset.timestamp = med.timestamp;

                medDiv.innerHTML = `
                    <div class="flex justify-content-between align-items-center text-light">
                        <div class="medikament-compact">
                            <span style="color:#a2a2a2">${med.zeit}</span>
                            <span>${med.wirkstoff}</span>
                            <span>${med.dosierung} ${med.einheit}</span>
                            <span>${med.applikation}</span>
                        </div>
                    </div>
                `;

                medDiv.addEventListener('click', function() {
                    selectMedikament(this);
                });

                medDiv.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#555';
                });

                medDiv.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.backgroundColor = 'transparent';
                    }
                });

                listContainer.appendChild(medDiv);
            });
        }

        function selectMedikament(element) {
            document.querySelectorAll('.medikament-item').forEach(item => {
                item.classList.remove('selected');
                item.style.backgroundColor = 'transparent';
            });

            element.classList.add('selected');
            element.style.backgroundColor = '#666';
        }

        function deleteSelectedMedikament() {
            const selectedItem = document.querySelector('.medikament-item.selected');
            if (!selectedItem) {
                showMedikamentToast('Bitte wählen Sie ein Medikament zum Löschen aus.', 'warning');
                return;
            }

            const timestamp = selectedItem.dataset.timestamp;
            const enr = new URLSearchParams(window.location.search).get('enr');

            fetch('./save_medikament', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${enr}&action=delete&timestamp=${timestamp}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('erfolgreich')) {
                        loadMedikamente();
                        showMedikamentToast('Medikament erfolgreich gelöscht.', 'success');
                    } else {
                        showMedikamentToast('Fehler beim Löschen: ' + data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMedikamentToast('Fehler beim Löschen des Medikaments.', 'error');
                });
        }

        function resetForm() {
            document.getElementById('medis-select').value = '';
            document.getElementById('medis-time').value = getCurrentTime();
            document.getElementById('medis-admission').value = '';
            document.getElementById('medis-concentration').value = '';
            document.getElementById('medis-unit').value = '';
        }

        function getCurrentTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            return `${hours}:${minutes}`;
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>