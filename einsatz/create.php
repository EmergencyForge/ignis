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
        $started_at = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert incident
            $stmt = $pdo->prepare("INSERT INTO intra_fire_incidents (incident_number, location, keyword, caller_name, caller_contact, started_at, leader_id, owner_type, owner_name, owner_contact, notes, status, created_by, location_x, location_y) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'in_sichtung', ?, ?, ?)");
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

// Fetch leaders (Mitarbeiter)
$leaders = [];
try {
    $stmt = $pdo->query("SELECT id, fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore for now
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
    <style>
        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar {
            width: 8px;
        }

        html::-webkit-scrollbar-track,
        body::-webkit-scrollbar-track,
        .sidebar-nav::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        html::-webkit-scrollbar-thumb,
        body::-webkit-scrollbar-thumb,
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: #4a4a4a;
            border-radius: 4px;
        }

        html::-webkit-scrollbar-thumb:hover,
        body::-webkit-scrollbar-thumb:hover,
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: #5a5a5a;
        }

        .sidebar-nav {
            overflow-y: auto;
        }

        .sidebar-nav .nav-link {
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .sidebar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav .nav-link.active {
            background-color: rgba(13, 110, 253, 0.5);
        }

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
        <!-- Sidebar Navigation -->
        <div class="sidebar-nav" style="width: 250px; min-height: 100vh; background-color: #1a1a1a; border-right: 1px solid #333;">
            <div class="p-3">
                <div class="text-center mb-3">
                    <img src="https://emergencyforge.de/assets/img/defaultLogo.webp" alt="EmergencyForge Logo" style="max-width: 120px; height: auto;">
                </div>

                <!-- Vehicle Login Info -->
                <?php if (isset($_SESSION['einsatz_vehicle_name'])): ?>
                    <div class="card bg-dark mb-3" style="font-size: 0.85rem;">
                        <div class="card-body p-2">
                            <div class="text-muted small mb-1">Angemeldet auf:</div>
                            <div class="fw-bold">
                                <i class="fas fa-truck me-1"></i>
                                <?= htmlspecialchars($_SESSION['einsatz_vehicle_name']) ?>
                            </div>
                            <?php if (isset($_SESSION['einsatz_operator_name'])): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($_SESSION['einsatz_operator_name']) ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= BASE_PATH ?>einsatz/login-fahrzeug.php?logout=1" class="btn btn-sm btn-outline-light mt-2 w-100">
                                <i class="fas fa-sign-out-alt me-1"></i>Abmelden
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="<?= BASE_PATH ?>einsatz/create.php">
                            <i class="fa-solid fa-plus me-2"></i>Neuer Einsatz
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/list.php">
                            <i class="fa-solid fa-list me-2"></i>Meine Einsätze
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/asu.php">
                            <i class="fa-solid fa-mask-ventilator me-2"></i>AS-Überwachung
                        </a>
                    </li>

                    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                        <li class="nav-item mt-4">
                            <small class="text-muted px-3 d-block mb-2">VERWALTUNG</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/admin/list.php">
                                <i class="fa-solid fa-shield-alt me-2"></i>Alle Einsätze
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

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
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
                                    <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['fullname']) ?></option>
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