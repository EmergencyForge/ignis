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

if (!isset($_SESSION['userid'])) {
    return;
}

// Flags für asynchrone Background-Requests (per AJAX statt blockierend)
$needsHeartbeat = false;
$needsCacheRefresh = false;

try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }

    // Admin-Status prüfen - direkt aus Session lesen (full_admin oder admin Permission)
    $isAdmin = false;
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        $isAdmin = in_array('full_admin', $_SESSION['permissions']) || in_array('admin', $_SESSION['permissions']);
    }

    // === TELEMETRIE: Nur prüfen ob nötig, NICHT synchron senden ===
    if ($isAdmin) {
        $telemetryManager = new TelemetryManager($pdo);
        if ($telemetryManager->isEnabled() && $telemetryManager->shouldSendHeartbeat()) {
            $needsHeartbeat = true;
        }
    }

    // === ANNOUNCEMENTS: Gecachte Daten laden OHNE blockierenden Refresh ===
    $announcementManager = new GlobalAnnouncementManager($pdo);
    $needsCacheRefresh = $announcementManager->isCacheStale();
    $announcements = $announcementManager->getActiveAnnouncements($_SESSION['userid'], $isAdmin, true);

    if (empty($announcements) && !$needsCacheRefresh) {
        // Keine Announcements und kein Refresh nötig — nur Background-JS ausgeben falls Heartbeat nötig
        if ($needsHeartbeat): ?>
            <script>
                fetch('<?= BASE_PATH ?>api/telemetry/background.php?action=heartbeat').catch(function() {});
            </script>
        <?php endif;
        return;
    }

    // Wenn Cache veraltet und keine gecachten Announcements da sind, trotzdem Seite laden
    // Der AJAX-Refresh holt neue Daten, die beim nächsten Seitenaufruf angezeigt werden
    if (empty($announcements)) {
        ?>
        <script>
            <?php if ($needsHeartbeat): ?>
                fetch('<?= BASE_PATH ?>api/telemetry/background.php?action=heartbeat').catch(function() {});
            <?php endif; ?>
            fetch('<?= BASE_PATH ?>api/telemetry/background.php?action=refresh-announcements').catch(function() {});
        </script>
<?php
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
$headerAccent = $hasCritical ? 'critical' : ($hasWarning ? 'warning' : 'update');

// Icon-Box-Farben für Announcement-Typen
$iconBoxColors = [
    'critical' => ['bg' => 'rgba(176, 58, 58, 0.12)', 'color' => '#d46b6b'],
    'warning'  => ['bg' => 'rgba(196, 154, 42, 0.12)', 'color' => '#ddb84a'],
    'update'   => ['bg' => 'rgba(74, 111, 165, 0.12)', 'color' => '#7ba3d4'],
    'info'     => ['bg' => 'rgba(42, 127, 143, 0.12)', 'color' => '#5bb8cc'],
    'success'  => ['bg' => 'rgba(58, 125, 68, 0.12)', 'color' => '#6abf76'],
];

// Announcement IDs für "Verstanden" Button sammeln
$allAnnouncementIds = array_column($announcements, 'announcement_id');
?>

<!-- EmergencyForge Announcements Modal -->
<div class="modal fade" id="efAnnouncementsModal" tabindex="-1" aria-labelledby="efAnnouncementsModalLabel" aria-hidden="true" data-auto-show="<?= $alreadyShown ? 'false' : 'true' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content ef-modal-content">
            <!-- Header -->
            <div class="ef-modal-header <?= $headerAccent ?>">
                <div class="flex items-center gap-3">
                    <div class="ef-logo-container <?= $headerAccent ?>">
                        <i class="fa-solid fa-fire-flame-curved"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="efAnnouncementsModalLabel">
                            Offizielle Ankündigung
                        </h5>
                        <small class="ef-subtitle">von EmergencyForge</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>

            <!-- Body mit Announcements -->
            <div class="modal-body p-0">
                <?php foreach ($announcements as $index => $ann):
                    $config = $typeConfig[$ann['type']] ?? $typeConfig['info'];
                    $isAdminOnly = !empty($ann['admin_only']);
                    $iconColors = $iconBoxColors[$ann['type']] ?? $iconBoxColors['info'];
                ?>
                    <div class="announcement-item <?= $index > 0 ? 'border-t' : '' ?>"
                        data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
                        <div class="flex gap-3">
                            <!-- Icon -->
                            <div class="ef-announcement-icon" style="background: <?= $iconColors['bg'] ?>;">
                                <i class="fa-solid <?= $config['icon'] ?>" style="color: <?= $iconColors['color'] ?>;"></i>
                            </div>

                            <!-- Content -->
                            <div class="announcement-content grow">
                                <div class="flex items-center flex-wrap gap-2 mb-2">
                                    <span class="badge bg-<?= $config['badge'] ?>"><?= $config['label'] ?></span>
                                    <?php if ($isAdminOnly): ?>
                                        <span class="badge ef-badge-admin">
                                            <i class="fa-solid fa-shield-halved mr-1"></i>Nur für Admins
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($ann['valid_until'])): ?>
                                        <small class="ef-meta-text">
                                            <i class="fa-regular fa-clock mr-1"></i>
                                            Gültig bis <?= \App\Helpers\DateTimeHelper::formatDateLocal($ann['valid_until']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <h6 class="ef-announcement-title"><?= htmlspecialchars($ann['title']) ?></h6>

                                <?php if (!empty($ann['message'])): ?>
                                    <div class="announcement-message"><?= $parsedown->text($ann['message']) ?></div>
                                <?php endif; ?>

                                <div class="flex items-center gap-2 flex-wrap mt-3">
                                    <?php if (!empty($ann['link'])): ?>
                                        <a href="<?= htmlspecialchars($ann['link']) ?>" class="btn btn-<?= $config['badge'] ?> btn-sm" target="_blank">
                                            <i class="fa-solid fa-external-link mr-1"></i> Mehr erfahren
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm dismiss-single-btn"
                                        data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
                                        <i class="fa-solid fa-eye-slash mr-1"></i> Ausblenden
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="ef-modal-footer">
                <small class="ef-meta-text">
                    <i class="fa-solid fa-shield-halved mr-1"></i>
                    Diese Nachricht stammt von EmergencyForge
                </small>
                <button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" id="efDismissAllBtn">
                    <i class="fa-solid fa-check mr-1"></i> Verstanden
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Trigger Button für manuelles Öffnen -->
<div id="efAnnouncementsTrigger" class="fixed" style="bottom: 20px; right: 20px; z-index: 1040; display: none;">
    <button type="button" class="btn btn-<?= $hasCritical ? 'danger' : ($hasWarning ? 'warning' : 'primary') ?> rounded-full shadow-lg relative"
        data-bs-toggle="modal" data-bs-target="#efAnnouncementsModal">
        <i class="fa-solid fa-bullhorn mr-2"></i>
        <span class="hidden d-sm-inline"><?= count($announcements) ?> Ankündigung<?= count($announcements) > 1 ? 'en' : '' ?></span>
        <span class="absolute top-0 start-100 translate-middle badge rounded-full bg-[#b03a3a] sm:hidden">
            <?= count($announcements) ?>
        </span>
    </button>
</div>

<style>
    /* EmergencyForge Announcements Modal Styles */
    #efAnnouncementsModal .ef-modal-content {
        background: #29282f;
        border: 1px solid var(--darkgray, #3d3a44);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }

    #efAnnouncementsModal .ef-modal-header {
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--darkgray, #3d3a44);
        background: rgba(255, 255, 255, 0.02);
    }

    #efAnnouncementsModal .ef-modal-header .modal-title {
        font-size: 0.9rem;
        font-weight: 500;
        color: #fff;
    }

    #efAnnouncementsModal .ef-subtitle {
        font-size: 0.72rem;
        color: var(--text-dimmed, #818189);
    }

    #efAnnouncementsModal .ef-logo-container {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.1rem;
    }

    #efAnnouncementsModal .ef-logo-container.critical {
        background: rgba(176, 58, 58, 0.15);
        color: #d46b6b;
    }

    #efAnnouncementsModal .ef-logo-container.warning {
        background: rgba(196, 154, 42, 0.15);
        color: #ddb84a;
    }

    #efAnnouncementsModal .ef-logo-container.update {
        background: rgba(74, 111, 165, 0.15);
        color: #7ba3d4;
    }

    #efAnnouncementsModal .announcement-item {
        padding: 1rem 1.25rem;
        transition: background-color 0.15s ease;
    }

    #efAnnouncementsModal .announcement-item.border-top {
        border-top: 1px solid rgba(255, 255, 255, 0.03) !important;
    }

    #efAnnouncementsModal .announcement-item:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }

    #efAnnouncementsModal .ef-announcement-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }

    #efAnnouncementsModal .ef-announcement-title {
        font-weight: 500;
        font-size: 0.88rem;
        color: #fff;
        margin-bottom: 0.25rem;
    }

    #efAnnouncementsModal .ef-badge-admin {
        background: rgba(255, 255, 255, 0.06);
        color: var(--text-dimmed, #818189);
    }

    #efAnnouncementsModal .ef-meta-text {
        font-size: 0.72rem;
        color: var(--text-dimmed, #818189);
    }

    #efAnnouncementsModal .ef-modal-footer {
        padding: 0.65rem 1.25rem;
        border-top: 1px solid var(--darkgray, #3d3a44);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.02);
    }

    /* Markdown Formatierung */
    #efAnnouncementsModal .announcement-message {
        line-height: 1.6;
        font-size: 0.8rem;
        color: var(--text-dimmed, #818189);
    }

    #efAnnouncementsModal .announcement-message p {
        margin-bottom: 0.75rem;
    }

    #efAnnouncementsModal .announcement-message p:last-child {
        margin-bottom: 0;
    }

    #efAnnouncementsModal .announcement-message strong {
        font-weight: 600;
        color: var(--text-normal, #bbbac1);
    }

    #efAnnouncementsModal .announcement-message code {
        background: rgba(255, 255, 255, 0.08);
        padding: 0.2em 0.4em;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    #efAnnouncementsModal .announcement-message pre {
        background: var(--body-bg-darker, #232128);
        padding: 0.75rem;
        border-radius: 6px;
        overflow-x: auto;
        margin-bottom: 0.75rem;
    }

    #efAnnouncementsModal .announcement-message pre code {
        background: none;
        padding: 0;
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
        color: #7ba3d4;
        text-decoration: underline;
    }

    #efAnnouncementsModal .announcement-message a:hover {
        color: #92b5e0;
    }

    #efAnnouncementsModal .announcement-message blockquote {
        border-left: 3px solid var(--darkgray, #3d3a44);
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
        font-weight: 500;
        color: #fff;
    }

    #efAnnouncementsModal .announcement-message h1 {
        font-size: 1.3rem;
    }

    #efAnnouncementsModal .announcement-message h2 {
        font-size: 1.15rem;
    }

    #efAnnouncementsModal .announcement-message h3 {
        font-size: 1rem;
    }

    #efAnnouncementsModal .announcement-message h4 {
        font-size: 0.9rem;
    }

    #efAnnouncementsModal .announcement-message hr {
        margin: 1rem 0;
        border-color: var(--darkgray, #3d3a44);
        opacity: 0.5;
    }

    /* Pulse Animation für Trigger Button bei kritischen Meldungen */
    #efAnnouncementsTrigger .btn-danger {
        animation: ef-pulse 2s infinite;
    }

    @keyframes ef-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(176, 58, 58, 0.6);
        }

        70% {
            box-shadow: 0 0 0 12px rgba(176, 58, 58, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(176, 58, 58, 0);
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
                dismissAllBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Wird gespeichert...';

                // Alle Announcements nacheinander dismissan
                Promise.all(allAnnouncementIds.map(id =>
                        fetch('<?= BASE_PATH ?>api/announcements/dismiss.php', {
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
                        dismissAllBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Verstanden';
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

                fetch('<?= BASE_PATH ?>api/announcements/dismiss.php', {
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
                        this.innerHTML = '<i class="fa-solid fa-eye-slash mr-1"></i> Ausblenden';
                    });
            });
        });

        // === Background-Requests (non-blocking, per AJAX) ===
        <?php if ($needsHeartbeat): ?>
            fetch('<?= BASE_PATH ?>api/telemetry/background.php?action=heartbeat').catch(function() {});
        <?php endif; ?>
        <?php if ($needsCacheRefresh): ?>
            fetch('<?= BASE_PATH ?>api/telemetry/background.php?action=refresh-announcements').catch(function() {});
        <?php endif; ?>
    });
</script>