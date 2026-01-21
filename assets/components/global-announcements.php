<?php

/**
 * Globale Announcements Modal-Komponente & Automatische Telemetrie
 * 
 * - Zeigt offizielle Ankündigungen von EmergencyForge in einem Modal an
 * - Sendet automatisch Telemetrie-Heartbeat (1x pro 24h, nur für Admins)
 */

require_once __DIR__ . '/../../src/Telemetry/GlobalAnnouncementManager.php';
require_once __DIR__ . '/../../src/Telemetry/TelemetryManager.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Telemetry\GlobalAnnouncementManager;
use App\Telemetry\TelemetryManager;
use Parsedown;

if (!isset($_SESSION['userid'])) {
    return;
}

try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }

    // Admin-Status prüfen - direkt aus Session lesen (full_admin oder admin Permission)
    $isAdmin = false;
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        $isAdmin = in_array('full_admin', $_SESSION['permissions']) || in_array('admin', $_SESSION['permissions']);
    }

    // === AUTOMATISCHE TELEMETRIE (nur für Admins, 1x pro 24h) ===
    if ($isAdmin) {
        $telemetryManager = new TelemetryManager($pdo);
        if ($telemetryManager->isEnabled() && $telemetryManager->shouldSendHeartbeat()) {
            // Heartbeat im Hintergrund senden (non-blocking)
            // Wir setzen ein Session-Flag um mehrfaches Senden pro Request zu verhindern
            if (!isset($_SESSION['_telemetry_sending'])) {
                $_SESSION['_telemetry_sending'] = true;
                $telemetryManager->sendHeartbeat();
                unset($_SESSION['_telemetry_sending']);
            }
        }
    }

    // === ANNOUNCEMENTS ===
    $announcementManager = new GlobalAnnouncementManager($pdo);
    $announcements = $announcementManager->getActiveAnnouncements($_SESSION['userid'], $isAdmin);

    if (empty($announcements)) {
        return;
    }

    // Markdown Parser initialisieren
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true); // XSS-Schutz: Kein HTML in Markdown erlauben
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

// Announcement IDs für "Verstanden" Button sammeln
$allAnnouncementIds = array_column($announcements, 'announcement_id');
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
                    $isAdminOnly = !empty($ann['admin_only']);
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
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                    <span class="badge bg-<?= $config['badge'] ?>"><?= $config['label'] ?></span>
                                    <?php if ($isAdminOnly): ?>
                                        <span class="badge bg-dark">
                                            <i class="fa-solid fa-shield-halved me-1"></i>Nur für Admins
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($ann['valid_until'])): ?>
                                        <small class="text-muted">
                                            <i class="fa-regular fa-clock me-1"></i>
                                            Gültig bis <?= date('d.m.Y', strtotime($ann['valid_until'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <h6 class="fw-bold mb-2"><?= htmlspecialchars($ann['title']) ?></h6>

                                <?php if (!empty($ann['message'])): ?>
                                    <div class="text-muted mb-3 announcement-message"><?= $parsedown->text($ann['message']) ?></div>
                                <?php endif; ?>

                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if (!empty($ann['link'])): ?>
                                        <a href="<?= htmlspecialchars($ann['link']) ?>" class="btn btn-<?= $config['badge'] ?> btn-sm" target="_blank">
                                            <i class="fa-solid fa-external-link me-1"></i> Mehr erfahren
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm dismiss-single-btn"
                                        data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
                                        <i class="fa-solid fa-eye-slash me-1"></i> Ausblenden
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
                    <button type="button" class="btn btn-primary" id="efDismissAllBtn">
                        <i class="fa-solid fa-check me-1"></i> Verstanden
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

    /* Markdown Formatierung */
    #efAnnouncementsModal .announcement-message {
        line-height: 1.6;
    }

    #efAnnouncementsModal .announcement-message p {
        margin-bottom: 0.75rem;
    }

    #efAnnouncementsModal .announcement-message p:last-child {
        margin-bottom: 0;
    }

    #efAnnouncementsModal .announcement-message strong {
        font-weight: 600;
        color: var(--bs-body-color);
    }

    #efAnnouncementsModal .announcement-message em {
        font-style: italic;
    }

    #efAnnouncementsModal .announcement-message code {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.2em 0.4em;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    #efAnnouncementsModal .announcement-message pre {
        background: rgba(0, 0, 0, 0.3);
        padding: 1rem;
        border-radius: 6px;
        overflow-x: auto;
        margin-bottom: 0.75rem;
    }

    #efAnnouncementsModal .announcement-message pre code {
        background: none;
        padding: 0;
        border-radius: 0;
    }

    #efAnnouncementsModal .announcement-message ul,
    #efAnnouncementsModal .announcement-message ol {
        margin-left: 1.5rem;
        margin-bottom: 0.75rem;
    }

    #efAnnouncementsModal .announcement-message li {
        margin-bottom: 0.25rem;
    }

    #efAnnouncementsModal .announcement-message a {
        color: var(--bs-link-color);
        text-decoration: underline;
    }

    #efAnnouncementsModal .announcement-message a:hover {
        color: var(--bs-link-hover-color);
    }

    #efAnnouncementsModal .announcement-message blockquote {
        border-left: 3px solid rgba(255, 255, 255, 0.3);
        padding-left: 1rem;
        margin-left: 0;
        margin-bottom: 0.75rem;
        font-style: italic;
        opacity: 0.9;
    }

    #efAnnouncementsModal .announcement-message h1,
    #efAnnouncementsModal .announcement-message h2,
    #efAnnouncementsModal .announcement-message h3,
    #efAnnouncementsModal .announcement-message h4,
    #efAnnouncementsModal .announcement-message h5,
    #efAnnouncementsModal .announcement-message h6 {
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--bs-body-color);
    }

    #efAnnouncementsModal .announcement-message h1 { font-size: 1.5rem; }
    #efAnnouncementsModal .announcement-message h2 { font-size: 1.3rem; }
    #efAnnouncementsModal .announcement-message h3 { font-size: 1.1rem; }
    #efAnnouncementsModal .announcement-message h4 { font-size: 1rem; }
    #efAnnouncementsModal .announcement-message h5 { font-size: 0.9rem; }
    #efAnnouncementsModal .announcement-message h6 { font-size: 0.85rem; }

    #efAnnouncementsModal .announcement-message hr {
        margin: 1rem 0;
        opacity: 0.3;
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
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('efAnnouncementsModal');
        const trigger = document.getElementById('efAnnouncementsTrigger');
        const dismissAllBtn = document.getElementById('efDismissAllBtn');

        if (!modal) return;

        const bsModal = new bootstrap.Modal(modal);
        const autoShow = modal.dataset.autoShow === 'true';

        // Alle Announcement IDs
        const allAnnouncementIds = <?= json_encode($allAnnouncementIds) ?>;

        // Auto-show Modal wenn noch nicht in dieser Session gezeigt
        if (autoShow) {
            setTimeout(() => bsModal.show(), 500);
        } else {
            // Zeige Trigger-Button
            if (trigger) trigger.style.display = 'block';
        }

        // Nach Modal-Schließen: Zeige Trigger-Button
        modal.addEventListener('hidden.bs.modal', function() {
            // Nur zeigen wenn noch Announcements übrig sind
            const remaining = modal.querySelectorAll('.announcement-item');
            if (remaining.length > 0 && trigger) {
                trigger.style.display = 'block';
            }
        });

        // "Verstanden" Button - ALLE Announcements permanent ausblenden
        if (dismissAllBtn) {
            dismissAllBtn.addEventListener('click', function() {
                dismissAllBtn.disabled = true;
                dismissAllBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Wird gespeichert...';

                // Alle Announcements nacheinander dismissan
                Promise.all(allAnnouncementIds.map(id =>
                        fetch('<?= BASE_PATH ?>api/dismiss-announcement.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                announcement_id: id
                            })
                        })
                    ))
                    .then(() => {
                        bsModal.hide();
                        if (trigger) trigger.style.display = 'none';
                    })
                    .catch(err => {
                        console.error('Dismiss all failed:', err);
                        dismissAllBtn.disabled = false;
                        dismissAllBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Verstanden';
                    });
            });
        }

        // Einzelne Announcement ausblenden
        document.querySelectorAll('.dismiss-single-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const announcementId = this.dataset.announcementId;
                const item = this.closest('.announcement-item');

                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

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
                    .catch(err => {
                        console.error('Dismiss failed:', err);
                        this.disabled = false;
                        this.innerHTML = '<i class="fa-solid fa-eye-slash me-1"></i> Ausblenden';
                    });
            });
        });
    });
</script>