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

// Check if logged into vehicle (skip for admins and QM users)
if (!isset($_SESSION['einsatz_vehicle_id']) || !isset($_SESSION['einsatz_operator_id'])) {
    // Users with admin or fire.incident.qm permissions can bypass vehicle login
    if (!Permissions::check(['admin', 'fire.incident.qm'])) {
        Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
        header("Location: " . BASE_PATH . "einsatz/login-fahrzeug.php");
        exit();
    }
}

require __DIR__ . '/../assets/config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$activeTab = $_GET['tab'] ?? 'stammdaten';
if ($id <= 0) {
    Flash::error('Ungültige Einsatz-ID');
    header('Location: ' . BASE_PATH . 'index.php');
    exit();
}

// Clear viewed sessions for other incidents when switching between incidents
foreach (array_keys($_SESSION) as $key) {
    if (strpos($key, 'einsatz_viewed_') === 0 && $key !== 'einsatz_viewed_' . $id) {
        unset($_SESSION[$key]);
    }
}

// Load incident
try {
    $stmt = $pdo->prepare("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        Flash::error('Einsatz nicht gefunden');
        header('Location: ' . BASE_PATH . 'index.php');
        exit();
    }

    // Check if user's vehicle is assigned to this incident (only if vehicle login exists)
    $isAssigned = false;
    if (isset($_SESSION['einsatz_vehicle_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incident_vehicles WHERE incident_id = ? AND vehicle_id = ?");
        $stmt->execute([$id, $_SESSION['einsatz_vehicle_id']]);
        $isAssigned = $stmt->fetchColumn() > 0;
    }

    if (!$isAssigned && !Permissions::check(['admin', 'fire.incident.qm'])) {
        Flash::error('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet. Zugriff verweigert.');
        header('Location: ' . BASE_PATH . 'einsatz/list.php');
        exit();
    }
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden: ' . $e->getMessage());
    header('Location: ' . BASE_PATH . 'index.php');
    exit();
}

// Load vehicles for selection and attached
$allVehicles = [];
$attachedVehicles = [];
try {
    $allVehicles = $pdo->query("SELECT id, name, identifier, veh_type FROM intra_fahrzeuge WHERE active = 1 ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT v.*, f.name AS sys_name, f.veh_type AS sys_type FROM intra_fire_incident_vehicles v LEFT JOIN intra_fahrzeuge f ON v.vehicle_id = f.id WHERE v.incident_id = ? ORDER BY v.id ASC");
    $stmt->execute([$id]);
    $attachedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Load sitreps
$sitreps = [];
try {
    $stmt = $pdo->prepare("SELECT s.*, f.name AS sys_name FROM intra_fire_incident_sitreps s LEFT JOIN intra_fahrzeuge f ON s.vehicle_id = f.id WHERE s.incident_id = ? ORDER BY s.report_time ASC");
    $stmt->execute([$id]);
    $sitreps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Load ASU protocols
$asuProtocols = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM intra_fire_incident_asu WHERE incident_id = ? ORDER BY created_at ASC");
    $stmt->execute([$id]);
    $asuProtocols = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Helper for date/time formatting
function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        // Assume timestamp is already in Europe/Berlin timezone from database
        $dt = new DateTime($ts, new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

// Helper to format seconds as MM:SS
function fmt_elapsed(int|string $seconds): string
{
    $sec = (int)$seconds;
    if ($sec <= 0) return '00:00';
    $mins = floor($sec / 60);
    $secs = $sec % 60;
    return sprintf('%02d:%02d', $mins, $secs);
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
            font-weight: 500;
        }

        .sidebar-nav small {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
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
    <script src="<?= BASE_PATH ?>assets/js/dialogs.js"></script>
    <script>
        // Make BASE_PATH available to JavaScript
        const basePath = '<?= BASE_PATH ?>';
    </script>
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
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/create.php">
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

                    <li class="nav-item mt-4">
                        <small class="text-muted px-3 d-block mb-2">EINSATZPROTOKOLL</small>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'stammdaten' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=stammdaten">
                            <i class="fa-solid fa-info-circle me-2"></i>Stammdaten
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'bericht' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=bericht">
                            <i class="fa-solid fa-file-alt me-2"></i>Einsatzbericht
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'fahrzeuge' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=fahrzeuge">
                            <i class="fa-solid fa-truck me-2"></i>Einsatzmittel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'lagemeldungen' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=lagemeldungen">
                            <i class="fa-solid fa-broadcast-tower me-2"></i>Lagemeldungen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'lagekarte' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=lagekarte">
                            <i class="fa-solid fa-map-marked-alt me-2"></i>Lagekarte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'abschluss' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=abschluss">
                            <i class="fa-solid fa-check-circle me-2"></i>Abschluss
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $activeTab === 'log' ? 'active' : '' ?>" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $id ?>&tab=log">
                            <i class="fa-solid fa-history me-2"></i>Protokoll
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>Einsatzprotokoll</h1>
                    <div>
                        <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                            <span class="me-2 align-middle text-muted small">QM-Status:
                                <?php
                                if (!$incident['finalized']) {
                                    $badge = 'bg-secondary';
                                    $statusText = 'Unfertig';
                                } else {
                                    $badge = 'bg-danger';
                                    $statusText = 'Ungesichtet';
                                    if ($incident['status'] === 'gesichtet') {
                                        $badge = 'bg-success';
                                        $statusText = 'Gesichtet';
                                    } elseif ($incident['status'] === 'negativ') {
                                        $badge = 'bg-danger';
                                        $statusText = 'Negativ';
                                    }
                                }
                                ?>
                                <span class="badge <?= $badge ?>"><?= htmlspecialchars($statusText) ?></span>
                            </span>
                            <?php if ($incident['finalized']): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qmStatusModal">
                                    <i class="fa-solid fa-clipboard-check"></i> QM-Status ändern
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-2 text-muted">Einsatznummer: <?= htmlspecialchars($incident['incident_number'] ?? '–') ?></div>
                <?php App\Helpers\Flash::render(); ?>

                <!-- Tab Content -->
                <?php
                // Load the active tab content
                $validTabs = ['stammdaten', 'bericht', 'fahrzeuge', 'lagemeldungen', 'lagekarte', 'abschluss', 'log'];
                if (!in_array($activeTab, $validTabs)) {
                    $activeTab = 'stammdaten';
                }
                include __DIR__ . '/tabs/' . $activeTab . '.php';
                ?>
            </div>
        </div>
    </div>

    <!-- Finalize Confirm Modal -->
    <div class="modal fade" id="finalizeConfirmModal" tabindex="-1" aria-labelledby="finalizeConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="finalizeConfirmModalLabel">Einsatz abschließen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Möchten Sie diesen Einsatz wirklich abschließen?</strong></p>
                    <p class="text-muted small">Das Protokoll wird zur QM-Sichtung markiert und alle Daten werden gesperrt. Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="d-inline">
                        <input type="hidden" name="action" value="finalize">
                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                        <input type="hidden" name="return_tab" value="abschluss">
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-check-circle me-1"></i>Jetzt abschließen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
        <!-- QM Status Change Modal -->
        <div class="modal fade" id="qmStatusModal" tabindex="-1" aria-labelledby="qmStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qmStatusModalLabel">QM-Status ändern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="incident_id" value="<?= $id ?>">
                            <input type="hidden" name="return_tab" value="<?= $activeTab ?>">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="in_sichtung" <?= $incident['status'] === 'in_sichtung' ? 'selected' : '' ?>>Ungesichtet</option>
                                    <option value="gesichtet" <?= $incident['status'] === 'gesichtet' ? 'selected' : '' ?>>Gesichtet</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

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