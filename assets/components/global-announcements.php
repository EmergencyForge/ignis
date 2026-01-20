<?php

/**
 * Globale Announcements Modal-Komponente
 * 
 * Zeigt offizielle Ankündigungen von EmergencyForge in einem auffälligen Modal an.
 */

require_once __DIR__ . '/../../src/Telemetry/GlobalAnnouncementManager.php';

use App\Telemetry\GlobalAnnouncementManager;

if (!isset($_SESSION['userid'])) {
    return;
}

try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }

    $announcementManager = new GlobalAnnouncementManager($pdo);
    $announcements = $announcementManager->getActiveAnnouncements($_SESSION['userid']);

    if (empty($announcements)) {
        return;
    }
} catch (Exception $e) {
    error_log("Global announcements error: " . $e->getMessage());
    return;
}

// Prüfen ob Modal in dieser Session bereits angezeigt wurde
$sessionKey = 'ef_announcements_shown_' . md5(json_encode(array_column($announcements, 'announcement_id')));
$alreadyShown = isset($_SESSION[$sessionKey]);

// Session markieren für zukünftige Requests
if (!$alreadyShown) {
    $_SESSION[$sessionKey] = true;
}

// Typ-Konfiguration
$typeConfig = [
    'critical' => ['badge' => 'danger', 'icon' => 'fa-circle-exclamation', 'label' => 'Kritisch'],
    'warning' => ['badge' => 'warning', 'icon' => 'fa-triangle-exclamation', 'label' => 'Warnung'],
    'update' => ['badge' => 'primary', 'icon' => 'fa-arrow-up-from-bracket', 'label' => 'Update'],
    'info' => ['badge' => 'info', 'icon' => 'fa-circle-info', 'label' => 'Info'],
    'success' => ['badge' => 'success', 'icon' => 'fa-circle-check', 'label' => 'Erfolg'],
];

// Höchste Priorität ermitteln (für Modal-Styling)
$hasCritical = false;
$hasWarning = false;
foreach ($announcements as $ann) {
    if ($ann['type'] === 'critical') $hasCritical = true;
    if ($ann['type'] === 'warning') $hasWarning = true;
}
$headerClass = $hasCritical ? 'bg-danger' : ($hasWarning ? 'bg-warning text-dark' : 'bg-primary');
?>

<!-- EmergencyForge Announcements Modal -->
<div class="modal fade" id="efAnnouncementsModal" tabindex="-1" aria-labelledby="efAnnouncementsModalLabel" aria-hidden="true" data-auto-show="<?= $alreadyShown ? 'false' : 'true' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <!-- Header mit EmergencyForge Branding -->
            <div class="modal-header <?= $headerClass ?> text-white border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="ef-logo-container me-3">
                        <i class="fa-solid fa-fire-flame-curved fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold" id="efAnnouncementsModalLabel">
                            Offizielle Ankündigung
                        </h5>
                        <small class="opacity-75">von EmergencyForge</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>

            <!-- Body mit Announcements -->
            <div class="modal-body p-0">
                <?php foreach ($announcements as $index => $ann):
                    $config = $typeConfig[$ann['type']] ?? $typeConfig['info'];
                ?>
                    <div class="announcement-item p-4 <?= $index > 0 ? 'border-top' : '' ?>"
                        data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
                        <div class="d-flex">
                            <!-- Icon -->
                            <div class="announcement-icon me-3">
                                <div class="rounded-circle bg-<?= $config['badge'] ?> bg-opacity-10 p-3 d-flex align-items-center justify-content-center"
                                    style="width: 56px; height: 56px;">
                                    <i class="fa-solid <?= $config['icon'] ?> fa-xl text-<?= $config['badge'] ?>"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="announcement-content flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-<?= $config['badge'] ?> me-2"><?= $config['label'] ?></span>
                                    <?php if (!empty($ann['valid_until'])): ?>
                                        <small class="text-muted">
                                            <i class="fa-regular fa-clock me-1"></i>
                                            Gültig bis <?= date('d.m.Y', strtotime($ann['valid_until'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <h6 class="fw-bold mb-2"><?= htmlspecialchars($ann['title']) ?></h6>

                                <?php if (!empty($ann['message'])): ?>
                                    <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
                                <?php endif; ?>

                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($ann['link'])): ?>
                                        <a href="<?= htmlspecialchars($ann['link']) ?>" class="btn btn-<?= $config['badge'] ?> btn-sm" target="_blank">
                                            <i class="fa-solid fa-external-link me-1"></i> Mehr erfahren
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm dismiss-announcement-btn"
                                        data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
                                        <i class="fa-solid fa-eye-slash me-1"></i> Nicht mehr anzeigen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 bg-light py-3">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <small class="text-muted">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Diese Nachricht stammt von EmergencyForge
                    </small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Verstanden
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Trigger Button für manuelles Öffnen (wird angezeigt wenn Announcements vorhanden) -->
<div id="efAnnouncementsTrigger" class="position-fixed" style="bottom: 20px; right: 20px; z-index: 1040; display: none;">
    <button type="button" class="btn btn-<?= $hasCritical ? 'danger' : ($hasWarning ? 'warning' : 'primary') ?> rounded-pill shadow-lg position-relative"
        data-bs-toggle="modal" data-bs-target="#efAnnouncementsModal">
        <i class="fa-solid fa-bullhorn me-2"></i>
        <span class="d-none d-sm-inline"><?= count($announcements) ?> Ankündigung<?= count($announcements) > 1 ? 'en' : '' ?></span>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-sm-none">
            <?= count($announcements) ?>
        </span>
    </button>
</div>

<style>
    /* EmergencyForge Announcements Modal Styles */
    #efAnnouncementsModal .modal-content {
        background: var(--bs-body-bg, #1a1a1a);
    }

    #efAnnouncementsModal .announcement-item {
        transition: background-color 0.2s ease;
    }

    #efAnnouncementsModal .announcement-item:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }

    #efAnnouncementsModal .ef-logo-container {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #efAnnouncementsModal .modal-footer {
        background: rgba(0, 0, 0, 0.2) !important;
    }

    /* Pulse Animation für Trigger Button bei kritischen Meldungen */
    #efAnnouncementsTrigger .btn-danger {
        animation: ef-pulse 2s infinite;
    }

    @keyframes ef-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }

    /* Dismissed State */
    #efAnnouncementsModal .announcement-item.dismissed {
        opacity: 0.5;
        pointer-events: none;
    }

    #efAnnouncementsModal .announcement-item.dismissed::after {
        content: 'Ausgeblendet';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: bold;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('efAnnouncementsModal');
        const trigger = document.getElementById('efAnnouncementsTrigger');

        if (!modal) return;

        const bsModal = new bootstrap.Modal(modal);
        const autoShow = modal.dataset.autoShow === 'true';

        // Auto-show Modal wenn noch nicht in dieser Session gezeigt
        if (autoShow) {
            setTimeout(() => bsModal.show(), 500);
        } else {
            // Zeige Trigger-Button
            if (trigger) trigger.style.display = 'block';
        }

        // Nach Modal-Schließen: Zeige Trigger-Button
        modal.addEventListener('hidden.bs.modal', function() {
            if (trigger) trigger.style.display = 'block';
        });

        // Dismiss einzelne Announcements
        document.querySelectorAll('.dismiss-announcement-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const announcementId = this.dataset.announcementId;
                const item = this.closest('.announcement-item');

                // API-Call
                fetch('<?= BASE_PATH ?>api/dismiss-announcement.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            announcement_id: announcementId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Item ausblenden mit Animation
                            item.style.transition = 'all 0.3s ease';
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(20px)';

                            setTimeout(() => {
                                item.remove();

                                // Wenn keine Announcements mehr, Modal schließen
                                const remaining = modal.querySelectorAll('.announcement-item');
                                if (remaining.length === 0) {
                                    bsModal.hide();
                                    if (trigger) trigger.style.display = 'none';
                                }
                            }, 300);
                        }
                    })
                    .catch(err => console.error('Dismiss failed:', err));
            });
        });
    });
</script>