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

// Helper for date/time formatting
function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

// Get parameters from URL (pre-filled from incident if available)
$prefill_number = $_GET['incident_number'] ?? '';
$prefill_location = $_GET['location'] ?? '';
$prefill_incident_id = $_GET['incident_id'] ?? null;
$asu_id = $_GET['asu_id'] ?? null;

// Load existing ASU protocol to edit if asu_id is provided
$existingProtocol = null;
if ($asu_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_fire_incident_asu WHERE id = ?");
        $stmt->execute([$asu_id]);
        $existingProtocol = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingProtocol) {
            $protocolData = json_decode($existingProtocol['data'], true) ?? [];

            // Pre-fill basic data from existing protocol
            if (!$prefill_number && !empty($protocolData['missionNumber'])) {
                $prefill_number = $protocolData['missionNumber'];
            }
            if (!$prefill_location && !empty($protocolData['missionLocation'])) {
                $prefill_location = $protocolData['missionLocation'];
            }
        }
    } catch (PDOException $e) {
        // Silently fail if query doesn't work
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
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
    </style>
    <script src="<?= BASE_PATH ?>assets/js/dialogs.js"></script>
    <script>
        const basePath = '<?= BASE_PATH ?>';
    </script>
</head>

<body data-bs-theme="dark" data-page="asu">
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

                <ul class="nav flex-column" style="list-style: none; padding: 0; margin: 0;">
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
                        <a class="nav-link text-white active" href="<?= BASE_PATH ?>einsatz/asu.php">
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
                    <?php if ($prefill_incident_id): ?>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $prefill_incident_id ?>&tab=bericht">
                                <i class="fa-solid fa-arrow-left me-2"></i>Zurück zum Einsatz
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><i class="fa-solid fa-lungs me-2"></i>Atemschutzüberwachung (ASU)</h1>
                    <div class="current-time-display">
                        <i class="fa-solid fa-clock me-2"></i>
                        <span id="currentTime" style="font-family: monospace; font-size: 1.1rem; font-weight: bold;">00:00</span>
                    </div>
                </div>

                <!-- ASU-Überwachung -->
                <div class="intra__tile p-3">
                    <!-- <h4 class="mb-3"><i class="fa-solid fa-plus me-2"></i>Neue Atemschutzüberwachung</h4> -->

                    <form id="asuForm">
                        <input type="hidden" id="incidentId" value="<?= htmlspecialchars($prefill_incident_id ?? '') ?>">

                        <!-- Einsatzinformationen -->
                        <div class="card bg-dark mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">Einsatzinformationen</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatznummer *</label>
                                        <input type="text" class="form-control" id="missionNumber" value="<?= htmlspecialchars($prefill_number) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatzort *</label>
                                        <input type="text" class="form-control" id="missionLocation" value="<?= htmlspecialchars($prefill_location) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatzdatum *</label>
                                        <input type="text" class="form-control" id="missionDate" placeholder="TT.MM.JJJJ" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Überwacher *</label>
                                        <input type="text" class="form-control" id="supervisor" placeholder="Name des Überwachenden" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php require __DIR__ . '/tabs/asu_trupps.php'; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View ASU Protocol Modal -->
    <div class="modal fade" id="viewASUModal" tabindex="-1" aria-labelledby="viewASUModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="viewASUModalLabel">ASU-Protokoll Ansicht</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="asuProtocolView">
                    <!-- Content will be injected by JavaScript -->
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/asu.js"></script>

    <?php if ($existingProtocol): ?>
        <script>
            // Load existing protocol data
            document.addEventListener('DOMContentLoaded', function() {
                const protocolData = <?= json_encode(json_decode($existingProtocol['data'], true)) ?>;

                // Load basic information
                if (protocolData.missionDate) document.getElementById('missionDate').value = protocolData.missionDate;
                if (protocolData.supervisor) document.getElementById('supervisor').value = protocolData.supervisor;

                // Load trupp data for all 3 trupps
                for (let i = 1; i <= 3; i++) {
                    const truppKey = 'trupp' + i;
                    const truppData = protocolData[truppKey];

                    if (truppData) {
                        // Personal
                        if (truppData.tf) document.getElementById(truppKey + 'TF').value = truppData.tf;
                        if (truppData.tm1) document.getElementById(truppKey + 'TM1').value = truppData.tm1;
                        if (truppData.tm2) document.getElementById(truppKey + 'TM2').value = truppData.tm2;

                        // Mission details
                        if (truppData.startPressure) document.getElementById(truppKey + 'StartPressure').value = truppData.startPressure;
                        if (truppData.startTime) document.getElementById(truppKey + 'StartTime').value = truppData.startTime;
                        if (truppData.mission) document.getElementById(truppKey + 'Mission').value = truppData.mission;

                        // Checks
                        if (truppData.check1) document.getElementById(truppKey + 'Check1').value = truppData.check1;
                        if (truppData.check2) document.getElementById(truppKey + 'Check2').value = truppData.check2;

                        // End details
                        if (truppData.objective) document.getElementById(truppKey + 'Objective').value = truppData.objective;
                        if (truppData.retreat) document.getElementById(truppKey + 'Retreat').value = truppData.retreat;
                        if (truppData.end) document.getElementById(truppKey + 'End').value = truppData.end;
                        if (truppData.remarks) document.getElementById(truppKey + 'Remarks').value = truppData.remarks;

                        // Restore timer state
                        if (truppData.elapsedTime && truppData.elapsedTime > 0) {
                            truppTimers[i].elapsedSeconds = truppData.elapsedTime;
                            updateTruppDisplay(i);

                            // Auto-start timer if no end time is set
                            if (!truppData.end || truppData.end === '') {
                                console.log(`Auto-starting Trupp ${i} from saved state`);
                                startTrupp(i);
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>