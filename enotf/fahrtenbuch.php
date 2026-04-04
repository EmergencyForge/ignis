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
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

use App\Helpers\EnotfUrl;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if (!isset($_SESSION['fahrername']) || !isset($_SESSION['protfzg'])) {
    header("Location: " . EnotfUrl::page('loggedout'));
    exit();
}

$vehicleIdentifier = $_SESSION['protfzg'];
$fahrerName = $_SESSION['fahrername'];

// Resolve vehicle from DB
$vehicleStmt = $pdo->prepare("SELECT id, name, identifier FROM intra_fahrzeuge WHERE identifier = ? AND active = 1");
$vehicleStmt->execute([$vehicleIdentifier]);
$vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);

$vehicleId = $vehicle['id'] ?? null;
$vehicleName = $vehicle['name'] ?? $vehicleIdentifier;

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
        WHERE vehicle_identifier = :vid
        ORDER BY datum DESC, abfahrt DESC
    ");
    $stmt->execute([':vid' => $vehicleIdentifier]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "Fahrtenbuch &rsaquo; eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php
    $topbar_left_html = '
        <a href="' . EnotfUrl::page('overview') . '" class="edivi__iconlink">
            <i class="fa-solid fa-chevron-left"></i><br>
            <small>Zurück</small>
        </a>';
    $topbar_sync = ['leitstelle', 'session'];
    $topbar_show_notices = false;
    include __DIR__ . '/../assets/components/enotf/topbar.php';
    ?>
    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <div class="hr my-2" style="color:transparent"></div>

                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-light mb-0"><i class="fa-solid fa-book me-2"></i>Fahrtenbuch</h4>
                            <button type="button" class="edivi__nidabutton" id="toggleCreateForm">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>

                        <!-- Create Form -->
                        <div id="createFormWrap" style="display:none;" class="vehicle-info-card p-4 mb-4">
                            <h5 class="text-light mb-3">Neuer Eintrag</h5>
                            <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="return_to" value="enotf">
                                <input type="hidden" name="source" value="enotf">

                                <?php
                                $context = 'enotf';
                                include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                                ?>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Speichern</button>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="cancelCreateForm">Abbrechen</button>
                                </div>
                            </form>
                        </div>

                        <!-- Edit Form (hidden by default) -->
                        <div id="editFormWrap" style="display:none;" class="vehicle-info-card p-4 mb-4">
                            <h5 class="text-light mb-3">Eintrag bearbeiten</h5>
                            <form method="POST" action="<?= BASE_PATH ?>fahrtenbuch/actions.php" id="editForm">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" id="edit_id" value="">
                                <input type="hidden" name="return_to" value="enotf">
                                <input type="hidden" name="source" value="enotf">

                                <?php
                                $context = 'enotf';
                                // Reset entry for edit form (will be filled by JS)
                                $entry = null;
                                include __DIR__ . '/../assets/components/fahrtenbuch/_form-fields.php';
                                ?>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-save me-1"></i>Aktualisieren</button>
                                    <button type="button" class="btn btn-sm btn-outline-light" id="cancelEditForm">Abbrechen</button>
                                </div>
                            </form>
                        </div>

                        <!-- Entries List -->
                        <div class="vehicle-info-card p-4">
                            <?php
                            $context = 'enotf';
                            $canEdit = true;
                            $canDelete = false;
                            $actionsUrl = BASE_PATH . 'fahrtenbuch/actions.php';
                            include __DIR__ . '/../assets/components/fahrtenbuch/_list-table.php';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
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

                // Fill edit form fields (they're in the second set of form fields)
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
                        if (input.tagName === 'SELECT') {
                            input.value = fields[key];
                        } else if (input.tagName === 'TEXTAREA') {
                            input.value = fields[key];
                        } else {
                            input.value = fields[key];
                        }
                    }
                }

                editWrap.scrollIntoView({ behavior: 'smooth' });
            });
        });
    });
    </script>
</body>

</html>
