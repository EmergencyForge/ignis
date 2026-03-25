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

use App\Auth\Permissions;
use App\Helpers\Flash;

// Check if user authentication is required for vehicle login
if (defined('FIRE_INCIDENT_REQUIRE_USER_AUTH') && FIRE_INCIDENT_REQUIRE_USER_AUTH === true) {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_PATH . "login.php");
        exit();
    }
}

// Check if logged into vehicle
if (!isset($_SESSION['einsatz_vehicle_id']) || !isset($_SESSION['einsatz_operator_id'])) {
    Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
    header("Location: " . BASE_PATH . "einsatz/login-fahrzeug.php");
    exit();
}


// Clear all einsatz_viewed session variables when creating new incident
foreach (array_keys($_SESSION) as $key) {
    if (strpos($key, 'einsatz_viewed_') === 0) {
        unset($_SESSION[$key]);
    }
}

require __DIR__ . '/../assets/config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $keyword = trim($_POST['keyword'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $leader_id = !empty($_POST['leader_id']) ? (int)$_POST['leader_id'] : null;
    $incident_number = trim($_POST['incident_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $caller_name = trim($_POST['caller_name'] ?? '');
    $caller_contact = trim($_POST['caller_contact'] ?? '');

    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_contact = trim($_POST['owner_contact'] ?? '');

    // GTA Coordinates (optional)
    $location_x = !empty($_POST['location_x']) ? (float)$_POST['location_x'] : null;
    $location_y = !empty($_POST['location_y']) ? (float)$_POST['location_y'] : null;

    if ($incident_number === '') $errors[] = 'Einsatznummer ist erforderlich.';
    if ($location === '') $errors[] = 'Einsatzort ist erforderlich.';
    if ($keyword === '') $errors[] = 'Einsatzstichwort ist erforderlich.';
    if ($date === '' || $time === '') $errors[] = 'Datum und Uhrzeit sind erforderlich.';
    if ($leader_id === null) $errors[] = 'Einsatzleiter ist erforderlich.';

    $started_at = null;
    if ($date !== '' && $time !== '') {
        $startedDt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, new DateTimeZone('Europe/Berlin'));
        $started_at = $startedDt ? $startedDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert incident
            $stmt = $pdo->prepare("INSERT INTO intra_fire_incidents (incident_number, location, keyword, caller_name, caller_contact, started_at, leader_id, owner_type, owner_name, owner_contact, notes, status, created_by, location_x, location_y) VALUES (?,?,?,?,?,?,?,?,?,?,?, 0, ?, ?, ?)");
            $stmt->execute([
                $incident_number,
                $location,
                $keyword,
                $caller_name ?: null,
                $caller_contact ?: null,
                $started_at,
                $leader_id,
                null,
                $owner_name ?: null,
                $owner_contact ?: null,
                $notes ?: null,
                $_SESSION['userid'] ?? null,
                $location_x,
                $location_y
            ]);

            $incidentId = (int)$pdo->lastInsertId();

            // Automatically add the logged-in vehicle
            $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_vehicles (incident_id, vehicle_id, from_other_org, created_by) VALUES (?,?,0,?)");
            $stmt->execute([$incidentId, $_SESSION['einsatz_vehicle_id'], $_SESSION['userid'] ?? null]);

            // Log the creation
            $logEntry = "Einsatz erstellt";
            $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_log (incident_id, action_type, action_description, vehicle_id, operator_id, created_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $incidentId,
                'created',
                $logEntry,
                $_SESSION['einsatz_vehicle_id'],
                $_SESSION['einsatz_operator_id'],
                $_SESSION['userid'] ?? null
            ]);

            $pdo->commit();
            Flash::success('Einsatz wurde erstellt.');
            header('Location: ' . BASE_PATH . 'einsatz/view.php?id=' . $incidentId);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Fetch leaders (Mitarbeiter — lokal + Federation)
$leaders = \App\Federation\FederatedPersonnel::getLeaderOptions($pdo);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
    <style>
        .enotf-dropdown-container.form-select {
            padding: .375rem .75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--bs-body-color);
            background-color: var(--bs-body-bg);
            background-clip: padding-box;
            border: var(--bs-border-width) solid var(--bs-border-color);
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <div class="d-flex">
        <?php $einsatzActivePage = 'create'; include __DIR__ . '/../assets/components/einsatz-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <h1>Neuen Einsatz anlegen</h1>
                <?php App\Helpers\Flash::render(); ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="intra__tile p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Einsatznummer*</label>
                            <input type="text" name="incident_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzort*</label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzstichwort*</label>
                            <input type="text" name="keyword" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Datum*</label>
                            <input type="date" name="date" class="form-control" value="<?= (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Uhrzeit*</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzleiter*</label>
                            <select name="leader_id" class="form-select" required data-custom-dropdown="true" data-search-threshold="5">
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($leaders as $l): ?>
                                    <option value="<?= htmlspecialchars(is_int($l['id']) ? $l['id'] : $l['id']) ?>"><?= htmlspecialchars($l['fullname']) ?><?= $l['source_name'] ? ' [' . htmlspecialchars($l['source_name']) . ']' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Melder – Name</label>
                            <input type="text" name="caller_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Melder – Kontakt</label>
                            <input type="text" name="caller_contact" class="form-control">
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Name</label>
                            <input type="text" name="owner_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Kontakt</label>
                            <input type="text" name="owner_contact" class="form-control">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Optional: Angaben zum Geschädigten, Eigentümer oder Halter (Name/Kontakt).</small>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Einsatz erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>assets/js/enotf-custom-dropdown.js"></script>
    <script>
        // Initialize custom dropdowns when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                eNOTFCustomDropdown.init();
            });
        } else {
            eNOTFCustomDropdown.init();
        }
    </script>
</body>

</html>