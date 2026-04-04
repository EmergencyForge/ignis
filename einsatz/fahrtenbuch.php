<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Flash;

/** @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse */
if (defined('FIRE_INCIDENT_REQUIRE_USER_AUTH') && FIRE_INCIDENT_REQUIRE_USER_AUTH === true) {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_PATH . "login.php");
        exit();
    }
}

if (!isset($_SESSION['einsatz_vehicle_id']) || !isset($_SESSION['einsatz_operator_id'])) {
    Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
    header("Location: " . BASE_PATH . "einsatz/login-fahrzeug.php");
    exit();
}

require __DIR__ . '/../assets/config/database.php';

date_default_timezone_set('Europe/Berlin');

$vehicleId = (int)$_SESSION['einsatz_vehicle_id'];
$vehicleName = $_SESSION['einsatz_vehicle_name'] ?? 'Unbekannt';
$fahrerName = $_SESSION['einsatz_operator_name'] ?? '';

// Get vehicle identifier
$vehicleIdentifier = '';
$vStmt = $pdo->prepare("SELECT identifier FROM intra_fahrzeuge WHERE id = ? LIMIT 1");
$vStmt->execute([$vehicleId]);
$vehicleIdentifier = $vStmt->fetchColumn() ?: '';

// Fahrttypen
$fahrttypen = [
    'einsatzfahrt'   => 'Einsatzfahrt',
    'bewegungsfahrt' => 'Bewegungsfahrt',
    'werkstattfahrt' => 'Werkstattfahrt',
    'uebungsfahrt'   => 'Übungsfahrt',
    'dienstfahrt'    => 'Dienstfahrt',
    'sonstige'       => 'Sonstige',
];

// Load entries for this vehicle
$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM intra_fahrtenbuch
        WHERE vehicle_id = :vid
        ORDER BY datum DESC, abfahrt DESC
    ");
    $stmt->execute([':vid' => $vehicleId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="fahrtenbuch">
    <div class="d-flex">
        <?php
        $einsatzActivePage = 'fahrtenbuch';
        $einsatzExtraNav = '';
        include __DIR__ . '/../assets/components/einsatz-sidebar.php';
        ?>

        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><i class="fa-solid fa-book me-2"></i>Fahrtenbuch</h1>
                    <button type="button" class="btn btn-success btn-sm" id="toggleCreateForm">
                        <i class="fa-solid fa-plus me-1"></i>Neuer Eintrag
                    </button>
                </div>

                <?php Flash::render(); ?>

                <!-- Create Form -->
                <div id="createFormWrap" style="display:none;" class="intra__tile p-4 mb-3">
                    <h5 class="mb-3">Neuer Eintrag</h5>
                    <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="return_to" value="firetab">
                        <input type="hidden" name="source" value="firetab">

                        <?php
                        $context = 'firetab';
                        $entry = null;
                        include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Speichern</button>
                            <button type="button" class="btn btn-sm btn-ghost" id="cancelCreateForm">Abbrechen</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Form -->
                <div id="editFormWrap" style="display:none;" class="intra__tile p-4 mb-3">
                    <h5 class="mb-3">Eintrag bearbeiten</h5>
                    <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id" value="">
                        <input type="hidden" name="return_to" value="firetab">
                        <input type="hidden" name="source" value="firetab">

                        <?php
                        $context = 'firetab';
                        $entry = null;
                        include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                        ?>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Aktualisieren</button>
                            <button type="button" class="btn btn-sm btn-ghost" id="cancelEditForm">Abbrechen</button>
                        </div>
                    </form>
                </div>

                <!-- Entries List -->
                <div class="intra__tile p-4">
                    <?php
                    $context = 'firetab';
                    $canEdit = true;
                    $canDelete = false;
                    $actionsUrl = BASE_PATH . 'fahrtenbuch/actions.php';
                    include __DIR__ . '/../assets/components/fahrtenbuch/_list-table.php';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var createWrap = document.getElementById('createFormWrap');
        var editWrap = document.getElementById('editFormWrap');
        var toggleBtn = document.getElementById('toggleCreateForm');
        var cancelCreate = document.getElementById('cancelCreateForm');
        var cancelEdit = document.getElementById('cancelEditForm');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                editWrap.style.display = 'none';
                createWrap.style.display = createWrap.style.display === 'none' ? 'block' : 'none';
            });
        }
        if (cancelCreate) {
            cancelCreate.addEventListener('click', function() {
                createWrap.style.display = 'none';
            });
        }
        if (cancelEdit) {
            cancelEdit.addEventListener('click', function() {
                editWrap.style.display = 'none';
            });
        }

        // Edit buttons
        document.querySelectorAll('.fb-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                createWrap.style.display = 'none';
                editWrap.style.display = 'block';

                document.getElementById('edit_id').value = btn.dataset.id;

                var form = document.getElementById('editForm');
                var fields = {
                    'datum': btn.dataset.datum,
                    'abfahrt': btn.dataset.abfahrt,
                    'ankunft': btn.dataset.ankunft || '',
                    'fahrttyp': btn.dataset.fahrttyp,
                    'kilometer': btn.dataset.kilometer || '',
                    'stationierungsort': btn.dataset.stationierungsort || '',
                    'grund': btn.dataset.grund || ''
                };

                for (var key in fields) {
                    var input = form.querySelector('[name="' + key + '"]');
                    if (input) {
                        input.value = fields[key];
                    }
                }

                editWrap.scrollIntoView({ behavior: 'smooth' });
            });
        });
    });
    </script>
</body>

</html>
