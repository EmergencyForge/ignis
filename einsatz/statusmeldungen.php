<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Flash;

// Check if user authentication is required for vehicle login
/** @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse */
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

require __DIR__ . '/../assets/config/database.php';

$vehicleId = (int)$_SESSION['einsatz_vehicle_id'];
$vehicleName = $_SESSION['einsatz_vehicle_name'] ?? 'Unbekannt';

// Hole aktuellen Status des Fahrzeugs aus intra_fahrzeuge (primäre Quelle)
$currentStatus = null;
$statusSource = null;
$activeIncidentId = null;
$activeIncidentNumber = null;

try {
    // 1. Lese Fahrzeug-Status von intra_fahrzeuge
    $vehStmt = $pdo->prepare("
        SELECT current_status, status_source FROM intra_fahrzeuge WHERE id = :id LIMIT 1
    ");
    $vehStmt->execute([':id' => $vehicleId]);
    $vehRow = $vehStmt->fetch(PDO::FETCH_ASSOC);
    if ($vehRow && $vehRow['current_status'] !== null) {
        $currentStatus = $vehRow['current_status'];
        $statusSource = $vehRow['status_source'];
    }

    // 2. Prüfe ob ein aktiver Einsatz existiert
    $stmt = $pdo->prepare("
        SELECT fiv.current_status, fi.id AS incident_id, fi.incident_number
        FROM intra_fire_incident_vehicles fiv
        JOIN intra_fire_incidents fi ON fiv.incident_id = fi.id
        WHERE fiv.vehicle_id = :vehicle_id
        AND fi.finalized = 0
        ORDER BY fi.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':vehicle_id' => $vehicleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $activeIncidentId = (int)$row['incident_id'];
        $activeIncidentNumber = $row['incident_number'];
        // Wenn Status von einem Einsatz kommt (nicht no_dispatch), nehme den Einsatz-Status
        if ($statusSource !== 'no_dispatch' && $row['current_status'] !== null) {
            $currentStatus = $row['current_status'];
        }
    }
} catch (PDOException $e) {
    // Silently fail
}

// Status-Konfiguration (Farben aus fahrzeuge.php)
$statusConfig = [
    '0' => ['text' => '0', 'label' => 'Dringender Sprechwunsch', 'bg' => '#e0050e', 'color' => '#ffffff'],
    '1' => ['text' => '1', 'label' => 'Einsatzbereit Funk', 'bg' => '#5adf07', 'color' => '#000000'],
    '2' => ['text' => '2', 'label' => 'Einsatzbereit Wache', 'bg' => '#057b09', 'color' => '#ffffff'],
    '3' => ['text' => '3', 'label' => 'Einsatz übernommen', 'bg' => '#e6d611', 'color' => '#000000'],
    '4' => ['text' => '4', 'label' => 'Am Einsatzort', 'bg' => '#832209', 'color' => '#ffffff'],
    '5' => ['text' => '5', 'label' => 'Sprechwunsch', 'bg' => '#e99610', 'color' => '#000000'],
    '6' => ['text' => '6', 'label' => 'Nicht einsatzbereit', 'bg' => '#848292', 'color' => '#000000'],
];
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <style>
        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            width: 100%;
        }

        .status-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 12px;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.15s, border-color 0.15s;
            text-align: center;
            min-height: 100px;
            user-select: none;
            background-color: var(--bs-tertiary-bg);
            color: var(--bs-body-color);
        }

        .status-btn:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .status-btn:active {
            transform: scale(0.97);
        }

        .status-btn.active {
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.4), 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        .status-btn .status-number {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }

        .status-btn .status-label {
            font-size: 0.85rem;
            margin-top: 6px;
            font-weight: 500;
        }

        .status-btn-full {
            grid-column: 1 / -1;
        }

        .current-status-display {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            font-weight: bold;
            font-size: 1.5rem;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }

        .status-btn.sending {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
    <script>
        const basePath = '<?= BASE_PATH ?>';
    </script>
</head>

<body data-bs-theme="dark" data-page="statusmeldungen">
    <div class="d-flex">
        <?php
        $einsatzActivePage = 'statusmeldungen';
        $einsatzExtraNav = '';
        include __DIR__ . '/../assets/components/einsatz-sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><i class="fa-solid fa-signal me-2"></i>Statusmeldungen</h1>
                </div>

                <?php App\Helpers\Flash::render(); ?>

                <div class="intra__tile p-4 mb-3">
                    <!-- Fahrzeug-Info und aktueller Status -->
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-3">
                            <?php
                            $displayStatus = $statusConfig['6']; // Default: NG
                            $displayStatusText = 'NG';
                            if ($currentStatus !== null && isset($statusConfig[$currentStatus])) {
                                $displayStatus = $statusConfig[$currentStatus];
                                $displayStatusText = $currentStatus;
                            }
                            ?>
                            <div class="current-status-display" id="currentStatusBadge"
                                style="background-color: <?= $displayStatus['bg'] ?>; color: <?= $displayStatus['color'] ?>;">
                                <?= htmlspecialchars($displayStatusText) ?>
                            </div>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($vehicleName) ?></h5>
                            <small class="text-muted">
                                Aktueller Status: <strong id="currentStatusLabel"><?= htmlspecialchars($displayStatus['label']) ?></strong>
                                <?php if ($activeIncidentNumber && $statusSource !== 'no_dispatch'): ?>
                                    &middot; Einsatz #<?= htmlspecialchars($activeIncidentNumber) ?>
                                <?php elseif (!$activeIncidentId && $statusSource !== 'no_dispatch'): ?>
                                    &middot; <span class="text-warning">Kein aktiver Einsatz</span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <?php if (!$activeIncidentId): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            Ihr Fahrzeug ist keinem aktiven Einsatz zugeordnet. Statusmeldungen können erst gesendet werden, wenn ein Einsatz zugeordnet ist.
                        </div>
                    <?php else: ?>
                        <div class="status-grid">
                            <!-- Status-Buttons 1-6 -->
                            <?php foreach (['1', '2', '3', '4', '5', '6'] as $code): ?>
                                <?php
                                $cfg = $statusConfig[$code];
                                $isActive = $currentStatus === $code;
                                ?>
                                <div class="status-btn <?= $isActive ? 'active' : '' ?>"
                                    data-status="<?= $code ?>"
                                    data-bg="<?= $cfg['bg'] ?>" data-color="<?= $cfg['color'] ?>"
                                    <?= $isActive ? 'style="background-color: ' . $cfg['bg'] . '; color: ' . $cfg['color'] . ';"' : '' ?>
                                    onclick="sendStatus('<?= $code ?>')">
                                    <span class="status-number"><?= $cfg['text'] ?></span>
                                    <span class="status-label"><?= htmlspecialchars($cfg['label']) ?></span>
                                </div>
                            <?php endforeach; ?>

                            <!-- Status-Button 0 (volle Breite) -->
                            <?php
                            $cfg0 = $statusConfig['0'];
                            $isActive0 = $currentStatus === '0';
                            ?>
                            <div class="status-btn status-btn-full <?= $isActive0 ? 'active' : '' ?>"
                                data-status="0"
                                data-bg="<?= $cfg0['bg'] ?>" data-color="<?= $cfg0['color'] ?>"
                                <?= $isActive0 ? 'style="background-color: ' . $cfg0['bg'] . '; color: ' . $cfg0['color'] . ';"' : '' ?>
                                onclick="sendStatus('0')">
                                <span class="status-number"><?= $cfg0['text'] ?></span>
                                <span class="status-label"><?= htmlspecialchars($cfg0['label']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const statusConfig = <?= json_encode($statusConfig) ?>;
        const activeIncidentId = <?= $activeIncidentId ? (int)$activeIncidentId : 'null' ?>;
        let currentStatus = <?= $currentStatus !== null ? json_encode($currentStatus) : 'null' ?>;
        let statusSource = <?= $statusSource !== null ? json_encode($statusSource) : 'null' ?>;

        // Periodisch Status von Server abfragen (für no_dispatch Updates)
        setInterval(() => {
            fetch(basePath + 'einsatz/status-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_status' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.current_status !== undefined && data.current_status !== currentStatus) {
                    currentStatus = data.current_status;
                    statusSource = data.status_source || null;

                    // Update alle Buttons
                    document.querySelectorAll('.status-btn').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    const activeBtn = document.querySelector(`.status-btn[data-status="${currentStatus}"]`);
                    if (activeBtn) {
                        activeBtn.classList.add('active');
                        activeBtn.style.backgroundColor = activeBtn.dataset.bg;
                        activeBtn.style.color = activeBtn.dataset.color;
                    }

                    // Update Badge
                    const badge = document.getElementById('currentStatusBadge');
                    const label = document.getElementById('currentStatusLabel');
                    const cfg = statusConfig[currentStatus];
                    if (badge && cfg) {
                        badge.style.backgroundColor = cfg.bg;
                        badge.style.color = cfg.color;
                        badge.textContent = currentStatus;
                    }
                    if (label && cfg) {
                        label.textContent = cfg.label;
                    }
                }
            })
            .catch(() => {});
        }, 5000);

        function sendStatus(newStatus) {
            if (!activeIncidentId) return;
            if (newStatus === currentStatus) return;

            // Markiere Button als sendend
            const btn = document.querySelector(`.status-btn[data-status="${newStatus}"]`);
            if (btn) btn.classList.add('sending');

            fetch(basePath + 'einsatz/status-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_status',
                    incident_id: activeIncidentId,
                    new_status: newStatus
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Entferne active + Farbe von allen Buttons
                        document.querySelectorAll('.status-btn').forEach(b => {
                            b.classList.remove('active');
                            b.style.backgroundColor = '';
                            b.style.color = '';
                        });

                        // Setze active + Farbe auf neuen Button
                        if (btn) {
                            btn.classList.add('active');
                            btn.style.backgroundColor = btn.dataset.bg;
                            btn.style.color = btn.dataset.color;
                        }

                        currentStatus = newStatus;

                        // Aktualisiere Status-Badge oben
                        const badge = document.getElementById('currentStatusBadge');
                        const label = document.getElementById('currentStatusLabel');
                        const cfg = statusConfig[newStatus];
                        if (badge && cfg) {
                            badge.style.backgroundColor = cfg.bg;
                            badge.style.color = cfg.color;
                            badge.textContent = newStatus;
                        }
                        if (label && cfg) {
                            label.textContent = cfg.label;
                        }
                    } else {
                        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    }
                })
                .catch(err => {
                    console.error('Status-Update fehlgeschlagen:', err);
                    alert('Verbindungsfehler beim Senden des Status.');
                })
                .finally(() => {
                    if (btn) btn.classList.remove('sending');
                });
        }
    </script>
</body>

</html>
