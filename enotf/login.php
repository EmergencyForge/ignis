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
    $_SESSION['fahrername']      = $_POST['fahrername'];
    $_SESSION['fahrerquali']     = $_POST['fahrerquali'];
    $_SESSION['beifahrername']   = $_POST['beifahrername'] ?? null;
    $_SESSION['beifahrerquali']  = $_POST['beifahrerquali'] ?? null;
    $_SESSION['protfzg']         = $_POST['protfzg'];

    header("Location: overview.php");
    exit();
}

$stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
$fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);

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
        select[name="beifahrerquali"].is-invalid {
            padding-right: 0.5rem !important;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
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
                                        <input type="text" class="form-control my-2" name="fahrername" id="fahrername" placeholder="" autocomplete="off" required />
                                        <div class="name-dropdown" id="fahrername-dropdown"></div>
                                    </div>
                                    <label for="fahrername">Fahrer-Name</label>
                                </div>
                                <div class="col-3">
                                    <select class="form-select my-2" name="fahrerquali" id="fahrerquali" required data-custom-dropdown="true" data-placeholder="Qualifikation">
                                        <option value="" selected></option>
                                        <option value="RH">RettHelfer</option>
                                        <option value="RS/A">RettSan i.A.</option>
                                        <option value="RS">RettSan</option>
                                        <option value="NFS/A">NotSan i.A.</option>
                                        <option value="NFS">NotSan</option>
                                        <option value="NA">Notarzt</option>
                                    </select>
                                    <label for="fahrerquali">Qualifikation</label>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col">
                                    <div class="name-autocomplete-wrapper">
                                        <input type="text" class="form-control my-2" name="beifahrername" id="beifahrername" placeholder="" autocomplete="off" />
                                        <div class="name-dropdown" id="beifahrername-dropdown"></div>
                                    </div>
                                    <label for="beifahrername">Beifahrer-Name</label>
                                </div>
                                <div class="col-3">
                                    <select class="form-select my-2" name="beifahrerquali" id="beifahrerquali" data-custom-dropdown="true" data-placeholder="Qualifikation">
                                        <option value="" selected></option>
                                        <option value="RH">RettHelfer</option>
                                        <option value="RS/A">RettSan i.A.</option>
                                        <option value="RS">RettSan</option>
                                        <option value="NFS/A">NotSan i.A.</option>
                                        <option value="NFS">NotSan</option>
                                        <option value="NA">Notarzt</option>
                                    </select>
                                    <label for="beifahrerquali">Qualifikation</label>
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
                                        <option value="" disabled selected>Fahrzeug wählen</option>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE active = 1 AND rd_type IN (1, 2) ORDER BY priority ASC");
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
                                            echo "<option value='" . htmlspecialchars($row['identifier']) . "'>" . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['veh_type']) . ")</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <hr class="my-5" style="color: transparent">
                            <hr class="my-5" style="color: transparent">
                            </hr>
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
    <script>
        // Name suggestions data from PHP
        const nameSuggestions = <?= json_encode($fullnames, JSON_UNESCAPED_UNICODE) ?>;

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

        // Initialize autocomplete for both name fields
        setupNameAutocomplete('fahrername', 'fahrername-dropdown');
        setupNameAutocomplete('beifahrername', 'beifahrername-dropdown');

        document.getElementById('crew__delete').addEventListener('click', function() {
            document.getElementById('fahrername').value = '';
            document.getElementById('fahrerquali').value = '';
            document.getElementById('beifahrername').value = '';
            document.getElementById('beifahrerquali').value = '';
        });

        document.getElementById('crew__switch').addEventListener('click', function() {
            const fName = document.getElementById('fahrername');
            const fQuali = document.getElementById('fahrerquali');
            const bName = document.getElementById('beifahrername');
            const bQuali = document.getElementById('beifahrerquali');

            [fName.value, bName.value] = [bName.value, fName.value];
            [fQuali.value, bQuali.value] = [bQuali.value, fQuali.value];
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>