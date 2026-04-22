<?php
/**
 * View: Fahrzeug-Statusmeldungen (S0–S6 Grid)
 *
 * @var string      $vehicleName
 * @var string|null $currentStatus
 * @var string|null $statusSource
 * @var int|null    $activeIncidentId
 * @var string|null $activeIncidentNumber
 * @var array       $statusConfig
 * @var \PDO        $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
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
    <div class="flex">
        <?php
        $einsatzActivePage = 'statusmeldungen';
        $einsatzExtraNav = '';
        include __DIR__ . '/../../assets/components/einsatz-sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto my-4">
                <div class="mb-4 flex items-center justify-between">
                    <h1><i class="fa-solid fa-signal me-2"></i>Statusmeldungen</h1>
                </div>

                <?php Flash::render(); ?>

                <div class="intra__tile mb-3 p-4">
                    <!-- Fahrzeug-Info und aktueller Status -->
                    <div class="mb-4 flex items-center gap-3">
                        <div>
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
                            <small class="text-gray-400">
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

        setInterval(() => {
            fetch(basePath + 'api/fire/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_status' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.current_status !== undefined && data.current_status !== currentStatus) {
                    currentStatus = data.current_status;
                    statusSource = data.status_source || null;

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

            const btn = document.querySelector(`.status-btn[data-status="${newStatus}"]`);
            if (btn) btn.classList.add('sending');

            fetch(basePath + 'api/fire/status.php', {
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
                        document.querySelectorAll('.status-btn').forEach(b => {
                            b.classList.remove('active');
                            b.style.backgroundColor = '';
                            b.style.color = '';
                        });

                        if (btn) {
                            btn.classList.add('active');
                            btn.style.backgroundColor = btn.dataset.bg;
                            btn.style.color = btn.dataset.color;
                        }

                        currentStatus = newStatus;

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
