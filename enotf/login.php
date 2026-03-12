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
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['login_mode'] ?? 'new';
    $vehicle = $_POST['protfzg'];

    if ($mode === 'join') {
        // Bestehender Session beitreten
        $joinPosition = $_POST['join_position'] ?? null;
        $joinName = $_POST['join_name'] ?? null;
        $joinQuali = $_POST['join_quali'] ?? null;

        if (!$joinPosition || !$joinName) {
            // Fallback: normale Anmeldung
            $mode = 'new';
        } else {
            // Aktive Session für dieses Fahrzeug finden
            $stmt = $pdo->prepare("SELECT id FROM intra_enotf_sessions WHERE vehicle_identifier = :vehicle AND active = 1 ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([':vehicle' => $vehicle]);
            $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSession) {
                $sessionId = $existingSession['id'];

                // Position in der Session updaten
                $posNameCol = $joinPosition . 'name';
                $posQualiCol = $joinPosition . 'quali';
                $updateStmt = $pdo->prepare("UPDATE intra_enotf_sessions SET $posNameCol = :name, $posQualiCol = :quali WHERE id = :id");
                $updateStmt->execute([':name' => $joinName, ':quali' => $joinQuali, ':id' => $sessionId]);

                // Session-Token generieren und Member anlegen
                $sessionToken = bin2hex(random_bytes(32));
                $memberStmt = $pdo->prepare("INSERT INTO intra_enotf_session_members (session_id, session_token, position) VALUES (:sid, :token, :position)");
                $memberStmt->execute([':sid' => $sessionId, ':token' => $sessionToken, ':position' => $joinPosition]);

                // Aktuelle Session-Daten laden
                $loadStmt = $pdo->prepare("SELECT * FROM intra_enotf_sessions WHERE id = :id");
                $loadStmt->execute([':id' => $sessionId]);
                $sessionData = $loadStmt->fetch(PDO::FETCH_ASSOC);

                $_SESSION['fahrername']      = $sessionData['fahrername'];
                $_SESSION['fahrerquali']     = $sessionData['fahrerquali'];
                $_SESSION['beifahrername']   = $sessionData['beifahrername'];
                $_SESSION['beifahrerquali']  = $sessionData['beifahrerquali'];
                $_SESSION['praktikantname']  = $sessionData['praktikantname'];
                $_SESSION['praktikantquali'] = $sessionData['praktikantquali'];
                $_SESSION['protfzg']         = $vehicle;
                $_SESSION['enotf_session_token'] = $sessionToken;
                $_SESSION['enotf_position']  = $joinPosition;

                header("Location: overview.php");
                exit();
            }
            // Falls keine aktive Session existiert: Fallback zu normaler Anmeldung
            $mode = 'new';
        }
    }

    if ($mode === 'new') {
        // Neue Session erstellen
        $_SESSION['fahrername']      = $_POST['fahrername'];
        $_SESSION['fahrerquali']     = $_POST['fahrerquali'];
        $_SESSION['beifahrername']   = $_POST['beifahrername'] ?? null;
        $_SESSION['beifahrerquali']  = $_POST['beifahrerquali'] ?? null;
        $_SESSION['praktikantname']  = $_POST['praktikantname'] ?? null;
        $_SESSION['praktikantquali'] = $_POST['praktikantquali'] ?? null;
        $_SESSION['protfzg']         = $vehicle;

        // Bestehende aktive Sessions für dieses Fahrzeug deaktivieren
        $deactivateStmt = $pdo->prepare("UPDATE intra_enotf_sessions SET active = 0 WHERE vehicle_identifier = :vehicle AND active = 1");
        $deactivateStmt->execute([':vehicle' => $vehicle]);

        // Neue Fahrzeug-Session anlegen
        $insertStmt = $pdo->prepare("
            INSERT INTO intra_enotf_sessions (vehicle_identifier, fahrername, fahrerquali, beifahrername, beifahrerquali, praktikantname, praktikantquali)
            VALUES (:vehicle, :fn, :fq, :bn, :bq, :pn, :pq)
        ");
        $insertStmt->execute([
            ':vehicle' => $vehicle,
            ':fn' => $_SESSION['fahrername'],
            ':fq' => $_SESSION['fahrerquali'],
            ':bn' => $_SESSION['beifahrername'],
            ':bq' => $_SESSION['beifahrerquali'],
            ':pn' => $_SESSION['praktikantname'],
            ':pq' => $_SESSION['praktikantquali'],
        ]);
        $sessionId = $pdo->lastInsertId();

        // Session-Token für den Ersteller generieren
        $sessionToken = bin2hex(random_bytes(32));

        // Fahrer ist immer besetzt, also Member anlegen
        $memberStmt = $pdo->prepare("INSERT INTO intra_enotf_session_members (session_id, session_token, position) VALUES (:sid, :token, :position)");
        $memberStmt->execute([':sid' => $sessionId, ':token' => $sessionToken, ':position' => 'fahrer']);

        // Wenn Beifahrer angegeben, auch als Member anlegen (gleicher Token da gleicher Browser)
        // Nein: Bei "Neue Besatzung" meldet ein Browser alle Positionen an.
        // Der Browser der die neue Besatzung erstellt, bekommt die Position "fahrer" zugewiesen.

        $_SESSION['enotf_session_token'] = $sessionToken;
        $_SESSION['enotf_position'] = 'fahrer';

        header("Location: overview.php");
        exit();
    }
}

$stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
$fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);

// Lade RD Qualifikationen mit Abkürzungen
$stmtQuali = $pdo->query("SELECT id, name, abkuerzung FROM intra_mitarbeiter_rdquali WHERE none = 0 AND abkuerzung IS NOT NULL ORDER BY priority ASC");
$qualifikationen = $stmtQuali->fetchAll(PDO::FETCH_ASSOC);

// Prefill-Modus: Vorausfüllen aus bestehender Session
$prefill = [];
if (isset($_GET['prefill']) && $_GET['prefill'] === '1' && isset($_SESSION['fahrername'])) {
    $prefill = [
        'fahrername'     => $_SESSION['fahrername'] ?? '',
        'fahrerquali'    => $_SESSION['fahrerquali'] ?? '',
        'beifahrername'  => $_SESSION['beifahrername'] ?? '',
        'beifahrerquali' => $_SESSION['beifahrerquali'] ?? '',
        'praktikantname' => $_SESSION['praktikantname'] ?? '',
        'praktikantquali'=> $_SESSION['praktikantquali'] ?? '',
        'protfzg'        => $_SESSION['protfzg'] ?? '',
    ];
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
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
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="row my-2 border-bottom border-light" id="edivi__login-title">
                        <div class="col">
                            <h5 class="fw-bold">Anmeldung</h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="row mb-2">
                                <div class="col">
                                    <div class="name-autocomplete-wrapper">
                                        <input type="text" class="form-control my-2" name="fahrername" id="fahrername" placeholder="" autocomplete="off" required value="<?= htmlspecialchars($prefill['fahrername'] ?? '') ?>" />
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
                                        <input type="text" class="form-control my-2" name="beifahrername" id="beifahrername" placeholder="" autocomplete="off" value="<?= htmlspecialchars($prefill['beifahrername'] ?? '') ?>" />
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
                                        <input type="text" class="form-control my-2" name="praktikantname" id="praktikantname" placeholder="" autocomplete="off" value="<?= htmlspecialchars($prefill['praktikantname'] ?? '') ?>" />
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
                                <div class="col"><button type="button" class="edivi__nidabutton w-100" id="crew__delete" name="crew__delete">Besatzung löschen</button></div>
                                <div class="col"><button type="button" class="edivi__nidabutton w-100" id="crew__switch" name="crew__switch">Fahrer / Beifahrer tauschen</button></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row">
                                <div class="col">
                                    <select name="protfzg" id="protfzg" class="form-select my-2" required data-custom-dropdown="true" data-search-threshold="5">
                                        <option value="" disabled <?= empty($prefill['protfzg'] ?? '') ? 'selected' : '' ?>>Fahrzeug wählen</option>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE active = 1 AND rd_type IN (1, 2) ORDER BY priority ASC");
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
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
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong style="color:#e0a800;">Es ist bereits eine Besatzung auf diesem Fahrzeug angemeldet</strong>
                                    </div>
                                    <div id="session-crew-list"></div>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="button" class="edivi__nidabutton flex-grow-1" id="btn-join-session">Beitreten</button>
                                        <button type="button" class="edivi__nidabutton flex-grow-1" id="btn-new-session">Neue Besatzung</button>
                                        <button type="button" class="edivi__nidabutton" id="btn-delete-session" style="background-color:#dc3545;border-color:#dc3545;aspect-ratio:1;padding:0;width:42px;min-width:42px;" title="Session löschen"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <!-- Join-Formular: wird eingeblendet wenn "Beitreten" geklickt -->
                            <div id="join-form-container" style="display:none;">
                                <div class="session-info-box">
                                    <strong class="d-block mb-2">Position wählen:</strong>
                                    <select id="join-position-select" class="form-select mb-2">
                                    </select>
                                    <div class="row mb-2">
                                        <div class="col">
                                            <div class="name-autocomplete-wrapper">
                                                <input type="text" class="form-control" id="join-name" placeholder="Name" autocomplete="off" />
                                                <div class="name-dropdown" id="join-name-dropdown"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <select id="join-quali" class="form-select" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                                <option value=""></option>
                                                <?php foreach ($qualifikationen as $quali): ?>
                                                    <option value="<?= htmlspecialchars($quali['abkuerzung']) ?>"><?= htmlspecialchars($quali['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" class="edivi__nidabutton w-100" id="btn-submit-join">Beitreten</button>
                                </div>
                            </div>
                            <div id="spacer-area">
                                <hr class="my-5" style="color: transparent">
                                <hr class="my-5" style="color: transparent">
                            </div>
                            <div class="row">
                                <div class="col text-end">
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

        document.getElementById('crew__delete').addEventListener('click', function() {
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

        document.getElementById('crew__switch').addEventListener('click', function() {
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

            const positions = [
                { key: 'fahrer', label: 'Fahrer', nameKey: 'fahrername', qualiKey: 'fahrerquali' },
                { key: 'beifahrer', label: 'Beifahrer', nameKey: 'beifahrername', qualiKey: 'beifahrerquali' },
                { key: 'praktikant', label: 'Praktikant', nameKey: 'praktikantname', qualiKey: 'praktikantquali' }
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

            const posLabels = { fahrer: 'Fahrer', beifahrer: 'Beifahrer', praktikant: 'Praktikant' };
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

            const confirmed = (typeof showConfirm === 'function')
                ? await showConfirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?', { title: 'Session beenden', confirmText: 'Beenden', cancelText: 'Abbrechen', danger: true })
                : confirm('Möchten Sie die aktive Session auf diesem Fahrzeug wirklich beenden?');

            if (!confirmed) return;

            fetch(basePath + 'api/enotf/delete-vehicle-session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicle: document.getElementById('protfzg').value })
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
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
