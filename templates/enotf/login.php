<?php
/**
 * View: eNOTF Login
 *
 * @var bool                            $charLocked
 * @var string                          $charName
 * @var array<int,string>               $fullnames
 * @var array<int,array<string,mixed>>  $qualifikationen
 * @var array<int,array<string,mixed>>  $vehicles
 * @var array<string,mixed>             $prefill
 * @var string                          $pinEnabled  ('true' / 'false')
 * @var \PDO                            $pdo
 */

use App\Helpers\EnotfUrl;

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

// POST-Handler, Daten-Loading und Auth-Gates liegen im EnotfController.
// Variablen werden via $data von renderView() im Template-Scope bereitgestellt.
$hasPrefill = !empty($prefill);
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../../assets/components/enotf/_head.php';
    ?>
    <style>
        .name-autocomplete-wrapper {
            position: relative;
        }

        .name-dropdown {
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

        .name-item {
            padding: 8px 12px;
            cursor: pointer;
            color: white;
            border-bottom: 1px solid #555;
        }

        .name-item:last-child {
            border-bottom: none;
        }

        .name-item:hover {
            background-color: #555;
        }

        /* Fix padding for invalid qualification select fields */
        select[name="fahrerquali"].is-invalid,
        select[name="beifahrerquali"].is-invalid,
        select[name="praktikantquali"].is-invalid {
            padding-right: 0.5rem !important;
        }

        /* Session Info Box */
        .session-info-box {
            background-color: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .session-info-box .session-crew-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
        }

        .session-info-box .session-crew-item .position-label {
            color: #888;
            font-size: 0.85rem;
        }

        .session-info-box .session-crew-item .crew-name {
            color: #fff;
        }

        .session-info-box .position-free {
            color: #6c757d;
            font-style: italic;
        }

        .join-position-select {
            background-color: #333;
            border: 1px solid #555;
            border-radius: 4px;
            padding: 6px 10px;
            color: #fff;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <!-- Normales Anmeldeformular -->
    <form name="form" method="post" action="" id="login-form-new">
        <input type="hidden" name="login_mode" value="new" />
        <input type="hidden" name="new" value="1" />
        <div class="w-full" id="edivi__container">
            <div class="row h-full">
                <div class="col" id="edivi__content">
                    <div class="row my-2 border-b border-light" id="edivi__login-title">
                        <div class="col">
                            <h5 class="font-bold">Anmeldung</h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <?php
                            // Char-Lock + Prefill: Bestimme eigene Position
                            $charLockOwnPosition = null;
                            if ($charLocked && !empty($prefill)) {
                                if (($prefill['fahrername'] ?? '') === $charName) $charLockOwnPosition = 'fahrer';
                                elseif (($prefill['beifahrername'] ?? '') === $charName) $charLockOwnPosition = 'beifahrer';
                                elseif (($prefill['praktikantname'] ?? '') === $charName) $charLockOwnPosition = 'praktikant';
                            }
                            $hasPrefill = !empty($prefill);
                            ?>
                            <?php if ($charLocked && !$hasPrefill): ?>
                                <!-- Char-Lock ohne Prefill: Positions-Wähler -->
                                <div class="row mb-3">
                                    <div class="col">
                                        <select class="form-select my-2" id="charlock-position" data-custom-dropdown="true" data-placeholder="Position wählen">
                                            <option value="fahrer" selected>Fahrer</option>
                                            <option value="beifahrer">Beifahrer</option>
                                            <option value="praktikant">Praktikant</option>
                                        </select>
                                        <label>Meine Position</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select my-2" id="charlock-quali" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                            <option value=""></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>"><?= htmlspecialchars($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Qualifikation</label>
                                    </div>
                                </div>
                                <div class="text-[var(--text-dimmed,#818189)] mb-2" style="font-size:0.85rem;">
                                    <i class="fa-solid fa-lock mr-1"></i>Anmeldung als: <strong><?= htmlspecialchars($charName) ?></strong>
                                </div>
                                <!-- Hidden fields: werden per JS befüllt -->
                                <input type="hidden" name="fahrername" id="fahrername" value="" />
                                <input type="hidden" name="fahrerquali" id="fahrerquali" value="" />
                                <input type="hidden" name="beifahrername" id="beifahrername" value="" />
                                <input type="hidden" name="beifahrerquali" id="beifahrerquali" value="" />
                                <input type="hidden" name="praktikantname" id="praktikantname" value="" />
                                <input type="hidden" name="praktikantquali" id="praktikantquali" value="" />
                            <?php else: ?>
                                <?php
                                    // Bei Char-Lock + Prefill: eigene Position readonly, fremde Positionen (außer Praktikant) readonly
                                    $fahrerRo = ''; $beifahrerRo = ''; $praktikantRo = '';
                                    if ($charLocked && $charLockOwnPosition) {
                                        $fahrerRo = ($charLockOwnPosition === 'fahrer' || ($charLockOwnPosition !== 'fahrer' && !empty($prefill['fahrername'] ?? ''))) ? 'readonly' : '';
                                        $beifahrerRo = ($charLockOwnPosition === 'beifahrer' || ($charLockOwnPosition !== 'beifahrer' && !empty($prefill['beifahrername'] ?? ''))) ? 'readonly' : '';
                                        // Praktikant ist IMMER frei editierbar
                                    }
                                ?>
                                <div class="row mb-2">
                                    <div class="col">
                                        <div class="name-autocomplete-wrapper">
                                            <input type="text" class="form-control my-2" name="fahrername" id="fahrername" autocomplete="off" required value="<?= htmlspecialchars($prefill['fahrername'] ?? '') ?>" <?= $fahrerRo ?> />
                                            <div class="name-dropdown" id="fahrername-dropdown"></div>
                                        </div>
                                        <label for="fahrername">Fahrer-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select my-2" name="fahrerquali" id="fahrerquali" required data-custom-dropdown="true" data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['fahrerquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>" <?= ($prefill['fahrerquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= htmlspecialchars($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="fahrerquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <div class="name-autocomplete-wrapper">
                                            <input type="text" class="form-control my-2" name="beifahrername" id="beifahrername" autocomplete="off" value="<?= htmlspecialchars($prefill['beifahrername'] ?? '') ?>" <?= $beifahrerRo ?> />
                                            <div class="name-dropdown" id="beifahrername-dropdown"></div>
                                        </div>
                                        <label for="beifahrername">Beifahrer-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select my-2" name="beifahrerquali" id="beifahrerquali" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['beifahrerquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>" <?= ($prefill['beifahrerquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= htmlspecialchars($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="beifahrerquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <div class="name-autocomplete-wrapper">
                                            <input type="text" class="form-control my-2" name="praktikantname" id="praktikantname" autocomplete="off" value="<?= htmlspecialchars($prefill['praktikantname'] ?? '') ?>" />
                                            <div class="name-dropdown" id="praktikantname-dropdown"></div>
                                        </div>
                                        <label for="praktikantname">Praktikant-Name</label>
                                    </div>
                                    <div class="col-3">
                                        <select class="form-select my-2" name="praktikantquali" id="praktikantquali" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                            <option value="" <?= empty($prefill['praktikantquali'] ?? '') ? 'selected' : '' ?>></option>
                                            <?php foreach ($qualifikationen as $quali): ?>
                                                <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>" <?= ($prefill['praktikantquali'] ?? '') === $quali['abkuerzung'] ? 'selected' : '' ?>><?= htmlspecialchars($quali['abkuerzung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="praktikantquali">Qualifikation</label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col"><button type="button" class="edivi__nidabutton w-full" id="crew__delete" name="crew__delete">Besatzung löschen</button></div>
                                    <div class="col"><button type="button" class="edivi__nidabutton w-full" id="crew__switch" name="crew__switch">Fahrer / Beifahrer tauschen</button></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col">
                            <div class="row">
                                <div class="col">
                                    <select name="protfzg" id="protfzg" class="form-select my-2" required data-custom-dropdown="true" data-search-threshold="5">
                                        <option value="" disabled <?= empty($prefill['protfzg'] ?? '') ? 'selected' : '' ?>>Fahrzeug wählen</option>
                                        <?php
                                        foreach ($vehicles as $row) {
                                            $selected = ($prefill['protfzg'] ?? '') === $row['identifier'] ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($row['identifier']) . "' $selected>" . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['veh_type']) . ")</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Session-Info: wird per JS eingeblendet wenn Fahrzeug aktive Session hat -->
                            <div id="session-info-container" style="display:none;">
                                <div class="session-info-box">
                                    <div class="flex justify-between items-center mb-2">
                                        <strong style="color:#e0a800;">Es ist bereits eine Besatzung auf diesem Fahrzeug angemeldet</strong>
                                    </div>
                                    <div id="session-crew-list"></div>
                                    <div class="mt-3 flex gap-2">
                                        <button type="button" class="edivi__nidabutton grow" id="btn-join-session">Beitreten</button>
                                        <button type="button" class="edivi__nidabutton grow" id="btn-new-session">Neue Besatzung</button>
                                        <button type="button" class="edivi__nidabutton" id="btn-delete-session" style="background-color:#dc3545;border-color:#dc3545;aspect-ratio:1;padding:0;width:42px;min-width:42px;" title="Session löschen"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <!-- Join-Formular: wird eingeblendet wenn "Beitreten" geklickt -->
                            <div id="join-form-container" style="display:none;">
                                <div class="session-info-box">
                                    <strong class="block mb-2">Position wählen:</strong>
                                    <select id="join-position-select" class="form-select mb-2">
                                    </select>
                                    <div class="row mb-2">
                                        <div class="col">
                                            <div class="name-autocomplete-wrapper">
                                                <input type="text" class="ignis-input" id="join-name" placeholder="Name" autocomplete="off" value="<?= $charLocked ? htmlspecialchars($charName) : '' ?>" <?= $charLocked ? 'readonly' : '' ?> />
                                                <div class="name-dropdown" id="join-name-dropdown"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <select id="join-quali" class="form-select" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                                <option value=""></option>
                                                <?php foreach ($qualifikationen as $quali): ?>
                                                    <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>"><?= htmlspecialchars($quali['abkuerzung']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" class="edivi__nidabutton w-full" id="btn-submit-join">Beitreten</button>
                                </div>
                            </div>
                            <div id="spacer-area">
                                <hr class="my-5" style="color: transparent">
                                <hr class="my-5" style="color: transparent">
                            </div>
                            <div class="row">
                                <div class="col text-right">
                                    <button type="submit" class="edivi__nidabutton" style="padding: 20px 40px" id="data__set" name="data__set">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>

    <!-- Verstecktes Beitritts-Formular -->
    <form id="login-form-join" method="post" action="" style="display:none;">
        <input type="hidden" name="login_mode" value="join" />
        <input type="hidden" name="protfzg" id="join-protfzg" value="" />
        <input type="hidden" name="join_position" id="join-position-hidden" value="" />
        <input type="hidden" name="join_name" id="join-name-hidden" value="" />
        <input type="hidden" name="join_quali" id="join-quali-hidden" value="" />
    </form>

    <script>
        // Name suggestions data from PHP
        const nameSuggestions = <?= json_encode($fullnames, JSON_UNESCAPED_UNICODE) ?>;
        const basePath = <?= json_encode(BASE_PATH) ?>;

        // Setup custom dropdown for name inputs
        function setupNameAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);

            if (!input || !dropdown) return;

            // Populate dropdown with all names initially
            function populateDropdown(filterValue = '') {
                dropdown.innerHTML = '';
                const filteredNames = nameSuggestions.filter(name =>
                    name.toLowerCase().includes(filterValue.toLowerCase())
                );

                filteredNames.forEach(name => {
                    const item = document.createElement('div');
                    item.className = 'name-item';
                    item.textContent = name;
                    item.addEventListener('click', function() {
                        input.value = name;
                        dropdown.style.display = 'none';
                    });
                    dropdown.appendChild(item);
                });

                return filteredNames.length > 0;
            }

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (populateDropdown(this.value)) {
                    dropdown.style.display = 'block';
                }
            });

            // Filter dropdown on input
            input.addEventListener('input', function() {
                if (populateDropdown(this.value)) {
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.name-autocomplete-wrapper') ||
                    (e.target.closest('.name-autocomplete-wrapper') &&
                        e.target.closest('.name-autocomplete-wrapper').querySelector('input') !== input)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Initialize autocomplete for all name fields
        setupNameAutocomplete('fahrername', 'fahrername-dropdown');
        setupNameAutocomplete('beifahrername', 'beifahrername-dropdown');
        setupNameAutocomplete('praktikantname', 'praktikantname-dropdown');
        setupNameAutocomplete('join-name', 'join-name-dropdown');

        document.getElementById('crew__delete')?.addEventListener('click', function() {
            // Text-Inputs leeren
            ['fahrername', 'beifahrername', 'praktikantname'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            // Custom-Dropdown-Selects zurücksetzen
            ['fahrerquali', 'beifahrerquali', 'praktikantquali'].forEach(function(id) {
                const el = document.getElementById(id);
                el.selectedIndex = 0;
                eNOTFCustomDropdown.refresh(el);
            });
        });

        document.getElementById('crew__switch')?.addEventListener('click', function() {
            const fName = document.getElementById('fahrername');
            const bName = document.getElementById('beifahrername');
            const fQuali = document.getElementById('fahrerquali');
            const bQuali = document.getElementById('beifahrerquali');

            // Namen tauschen
            [fName.value, bName.value] = [bName.value, fName.value];

            // Qualifikationen tauschen (selectedIndex für Custom Dropdown)
            const tmpIndex = fQuali.selectedIndex;
            fQuali.selectedIndex = bQuali.selectedIndex;
            bQuali.selectedIndex = tmpIndex;
            eNOTFCustomDropdown.refresh(fQuali);
            eNOTFCustomDropdown.refresh(bQuali);
        });

        // Session-Erkennung bei Fahrzeugwechsel
        let currentSessionData = null;

        function checkVehicleSession(vehicleId) {
            if (!vehicleId) {
                document.getElementById('session-info-container').style.display = 'none';
                document.getElementById('join-form-container').style.display = 'none';
                document.getElementById('spacer-area').style.display = '';
                return;
            }

            fetch(basePath + 'api/enotf/check-vehicle-session.php?vehicle=' + encodeURIComponent(vehicleId))
                .then(r => r.json())
                .then(data => {
                    if (data.active) {
                        currentSessionData = data;
                        showSessionInfo(data);
                    } else {
                        currentSessionData = null;
                        document.getElementById('session-info-container').style.display = 'none';
                        document.getElementById('join-form-container').style.display = 'none';
                        document.getElementById('spacer-area').style.display = '';
                    }
                })
                .catch(() => {
                    currentSessionData = null;
                    document.getElementById('session-info-container').style.display = 'none';
                });
        }

        function showSessionInfo(data) {
            const container = document.getElementById('session-info-container');
            const crewList = document.getElementById('session-crew-list');
            crewList.innerHTML = '';

            const positions = [{
                    key: 'fahrer',
                    label: 'Fahrer',
                    nameKey: 'fahrername',
                    qualiKey: 'fahrerquali'
                },
                {
                    key: 'beifahrer',
                    label: 'Beifahrer',
                    nameKey: 'beifahrername',
                    qualiKey: 'beifahrerquali'
                },
                {
                    key: 'praktikant',
                    label: 'Praktikant',
                    nameKey: 'praktikantname',
                    qualiKey: 'praktikantquali'
                }
            ];

            positions.forEach(pos => {
                const name = data.crew[pos.nameKey];
                const quali = data.crew[pos.qualiKey];
                const div = document.createElement('div');
                div.className = 'session-crew-item';

                if (name) {
                    div.innerHTML = '<span class="position-label">' + pos.label + ':</span><span class="crew-name">' +
                        escapeHtml(name) + (quali ? ' (' + escapeHtml(quali) + ')' : '') + '</span>';
                } else {
                    div.innerHTML = '<span class="position-label">' + pos.label + ':</span><span class="position-free">frei</span>';
                }
                crewList.appendChild(div);
            });

            container.style.display = '';
            document.getElementById('join-form-container').style.display = 'none';
            document.getElementById('spacer-area').style.display = 'none';

            // Beitreten-Button nur anzeigen wenn freie Positionen vorhanden
            const btnJoin = document.getElementById('btn-join-session');
            btnJoin.style.display = data.free_positions.length > 0 ? '' : 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Fahrzeug-Auswahl: onChange prüft ob aktive Session existiert
        const protfzgSelect = document.getElementById('protfzg');
        // Bei Custom-Dropdown: change-Event abfangen
        protfzgSelect.addEventListener('change', function() {
            checkVehicleSession(this.value);
        });

        // Beitreten-Button: Zeigt Join-Formular
        document.getElementById('btn-join-session').addEventListener('click', function() {
            if (!currentSessionData || currentSessionData.free_positions.length === 0) return;

            const posSelect = document.getElementById('join-position-select');
            posSelect.innerHTML = '';

            const posLabels = {
                fahrer: 'Fahrer',
                beifahrer: 'Beifahrer',
                praktikant: 'Praktikant'
            };
            currentSessionData.free_positions.forEach(pos => {
                const opt = document.createElement('option');
                opt.value = pos;
                opt.textContent = posLabels[pos] || pos;
                posSelect.appendChild(opt);
            });

            document.getElementById('session-info-container').style.display = 'none';
            document.getElementById('join-form-container').style.display = '';

            // Custom Dropdown für Quali initialisieren
            const joinQualiEl = document.getElementById('join-quali');
            if (joinQualiEl && typeof eNOTFCustomDropdown !== 'undefined') {
                eNOTFCustomDropdown.refresh(joinQualiEl);
            }
        });

        // Neue Besatzung: Standard-Formular verwenden, Session-Info ausblenden
        document.getElementById('btn-new-session').addEventListener('click', function() {
            currentSessionData = null;
            document.getElementById('session-info-container').style.display = 'none';
            document.getElementById('join-form-container').style.display = 'none';
            document.getElementById('spacer-area').style.display = '';
        });

        // Session löschen: aktive Session deaktivieren
        document.getElementById('btn-delete-session').addEventListener('click', async function() {
            if (!currentSessionData) return;

            const confirmed = (typeof showConfirm === 'function') ?
                await showConfirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?', {
                    title: 'Session beenden',
                    confirmText: 'Beenden',
                    cancelText: 'Abbrechen',
                    danger: true
                }) :
                confirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?');

            if (!confirmed) return;

            fetch(basePath + 'api/enotf/delete-vehicle-session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vehicle: document.getElementById('protfzg').value
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        currentSessionData = null;
                        document.getElementById('session-info-container').style.display = 'none';
                        document.getElementById('join-form-container').style.display = 'none';
                        document.getElementById('spacer-area').style.display = '';
                    }
                })
                .catch(() => {});
        });

        // Beitreten absenden
        document.getElementById('btn-submit-join').addEventListener('click', function() {
            const position = document.getElementById('join-position-select').value;
            const name = document.getElementById('join-name').value.trim();
            const quali = document.getElementById('join-quali').value;
            const vehicle = document.getElementById('protfzg').value;

            if (!name) {
                document.getElementById('join-name').classList.add('is-invalid');
                return;
            }
            document.getElementById('join-name').classList.remove('is-invalid');

            document.getElementById('join-protfzg').value = vehicle;
            document.getElementById('join-position-hidden').value = position;
            document.getElementById('join-name-hidden').value = name;
            document.getElementById('join-quali-hidden').value = quali;
            document.getElementById('login-form-join').submit();
        });

        // Prefill: Custom Dropdowns refreshen
        <?php if (!empty($prefill)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                ['fahrerquali', 'beifahrerquali', 'praktikantquali', 'protfzg'].forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el) eNOTFCustomDropdown.refresh(el);
                });
                // Session-Check für vorausgewähltes Fahrzeug auslösen
                if (protfzgSelect.value) {
                    checkVehicleSession(protfzgSelect.value);
                }
            });
        <?php endif; ?>

        // Char-Lock: Positions-Wähler → Hidden-Fields befüllen beim Submit
        <?php if ($charLocked && !$hasPrefill): ?>
        (function() {
            var charName = <?= json_encode($charName) ?>;
            var form = document.getElementById('login-form-new');
            if (!form) return;

            form.addEventListener('submit', function() {
                var pos = document.getElementById('charlock-position').value;
                var quali = document.getElementById('charlock-quali').value;

                // Alle zurücksetzen
                document.getElementById('fahrername').value = '';
                document.getElementById('fahrerquali').value = '';
                document.getElementById('beifahrername').value = '';
                document.getElementById('beifahrerquali').value = '';
                document.getElementById('praktikantname').value = '';
                document.getElementById('praktikantquali').value = '';

                // Gewählte Position befüllen
                document.getElementById(pos + 'name').value = charName;
                document.getElementById(pos + 'quali').value = quali;
            });
        })();
        <?php endif; ?>
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>