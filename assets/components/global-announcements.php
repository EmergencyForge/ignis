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
    if (!isset($pdo) || !$pdo instanceof PDO) {
        require_once __DIR__ . '/../config/database.php';
    }
    // Falls require_once oben no-op war (database.php in dieser Request schon
    // einmal geladen) und $pdo im aktuellen Scope undefiniert ist, aus dem
    // Container ziehen. Sonst wuerde TelemetryManager(null) failen.
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = app(PDO::class);
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
                fetch('<?= BASE_PATH ?>api/telemetry/background?action=heartbeat').catch(function() {});
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
                fetch('<?= BASE_PATH ?>api/telemetry/background?action=heartbeat').catch(function() {});
            <?php endif; ?>
            fetch('<?= BASE_PATH ?>api/telemetry/background?action=refresh-announcements').catch(function() {});
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

<!-- EmergencyForge Announcements — Inhalt liegt versteckt im DOM und wird
     über die Dialog-Komponente (assets/js/ui/dialog.js) geöffnet. -->
<div id="efAnnouncementsBody" class="ef-announcements-body" hidden data-auto-show="<?= $alreadyShown ? 'false' : 'true' ?>">
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
                                    <span class="ignis-chip ignis-chip--<?= $config['badge'] ?>"><?= $config['label'] ?></span>
                                    <?php if ($isAdminOnly): ?>
                                        <span class="ignis-chip ef-badge-admin">
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
                                        <a href="<?= htmlspecialchars($ann['link']) ?>" class="ignis-btn ignis-btn--<?= $config['badge'] ?> ignis-btn--sm" target="_blank">
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

    <div class="ef-announcements-source ef-meta-text">
        <i class="fa-solid fa-shield-halved mr-1"></i>
        Diese Nachricht stammt von EmergencyForge
    </div>
</div>

<!-- Trigger Button für manuelles Öffnen -->
<div id="efAnnouncementsTrigger" class="fixed" style="bottom: 20px; right: 20px; z-index: 1040; display: none;">
    <button type="button"
        class="ef-announce-fab ef-announce-fab--<?= $hasCritical ? 'critical' : ($hasWarning ? 'warning' : 'info') ?>"
        title="<?= count($announcements) ?> Ankündigung<?= count($announcements) > 1 ? 'en' : '' ?>"
        aria-label="<?= count($announcements) ?> Ankündigung<?= count($announcements) > 1 ? 'en' : '' ?> anzeigen">
        <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
        <span class="ef-announce-fab__count"><?= count($announcements) ?></span>
    </button>
</div>

<style>
    /* Floating-Trigger unten rechts: quadratisch mit leichter Rundung,
       Zähler als Eck-Badge. Bewusst eigene Klasse statt ignis-btn —
       der FAB hat feste Maße und eigene Severity-Farben. */
    .ef-announce-fab {
        width: 46px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 12px;
        color: #fff;
        font-size: 1.05rem;
        cursor: pointer;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.45);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .ef-announce-fab:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.55);
    }

    .ef-announce-fab--info {
        background: var(--btn-primary-bg, #4a6fa5);
    }

    .ef-announce-fab--warning {
        background: #a87b2d;
    }

    .ef-announce-fab--critical {
        background: #b03a3a;
    }

    .ef-announce-fab__count {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #b03a3a;
        border: 2px solid #1c1b21;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1;
    }

    /* EmergencyForge Announcements — Inhalt lebt im ignis-dialog__body.
       Dessen Innenabstand wird neutralisiert, damit die Einträge wie
       zuvor kante-zu-kante mit eigenen Abständen laufen. */
    .ignis-dialog__body:has(> .ef-announcements-body) {
        padding: 0;
    }

    .ef-announcements-body .announcement-item {
        padding: 1rem 1.25rem;
        transition: background-color 0.15s ease;
    }

    .ef-announcements-body .announcement-item.border-top {
        border-top: 1px solid rgba(255, 255, 255, 0.03) !important;
    }

    .ef-announcements-body .announcement-item:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }

    .ef-announcements-body .ef-announcement-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }

    .ef-announcements-body .ef-announcement-title {
        font-weight: 500;
        font-size: 0.88rem;
        color: #fff;
        margin-bottom: 0.25rem;
    }

    .ef-announcements-body .ef-badge-admin {
        background: rgba(255, 255, 255, 0.06);
        color: var(--text-dimmed, #818189);
    }

    .ef-announcements-body .ef-meta-text {
        font-size: 0.72rem;
        color: var(--text-dimmed, #818189);
    }

    .ef-announcements-body .ef-announcements-source {
        padding: 0.65rem 1.25rem;
        border-top: 1px solid var(--darkgray, #3d3a44);
        background: rgba(255, 255, 255, 0.02);
    }

    /* Markdown Formatierung */
    .ef-announcements-body .announcement-message {
        line-height: 1.6;
        font-size: 0.8rem;
        color: var(--text-dimmed, #818189);
    }

    .ef-announcements-body .announcement-message p {
        margin-bottom: 0.75rem;
    }

    .ef-announcements-body .announcement-message p:last-child {
        margin-bottom: 0;
    }

    .ef-announcements-body .announcement-message strong {
        font-weight: 600;
        color: var(--text-normal, #bbbac1);
    }

    .ef-announcements-body .announcement-message code {
        background: rgba(255, 255, 255, 0.08);
        padding: 0.2em 0.4em;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    .ef-announcements-body .announcement-message pre {
        background: var(--body-bg-darker, #232128);
        padding: 0.75rem;
        border-radius: 6px;
        overflow-x: auto;
        margin-bottom: 0.75rem;
    }

    .ef-announcements-body .announcement-message pre code {
        background: none;
        padding: 0;
    }

    .ef-announcements-body .announcement-message ul,
    .ef-announcements-body .announcement-message ol {
        margin-left: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .ef-announcements-body .announcement-message li {
        margin-bottom: 0.25rem;
    }

    .ef-announcements-body .announcement-message a {
        color: #7ba3d4;
        text-decoration: underline;
    }

    .ef-announcements-body .announcement-message a:hover {
        color: #92b5e0;
    }

    .ef-announcements-body .announcement-message blockquote {
        border-left: 3px solid var(--darkgray, #3d3a44);
        padding-left: 1rem;
        margin-left: 0;
        margin-bottom: 0.75rem;
        font-style: italic;
        opacity: 0.9;
    }

    .ef-announcements-body .announcement-message h1,
    .ef-announcements-body .announcement-message h2,
    .ef-announcements-body .announcement-message h3,
    .ef-announcements-body .announcement-message h4,
    .ef-announcements-body .announcement-message h5,
    .ef-announcements-body .announcement-message h6 {
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #fff;
    }

    .ef-announcements-body .announcement-message h1 {
        font-size: 1.3rem;
    }

    .ef-announcements-body .announcement-message h2 {
        font-size: 1.15rem;
    }

    .ef-announcements-body .announcement-message h3 {
        font-size: 1rem;
    }

    .ef-announcements-body .announcement-message h4 {
        font-size: 0.9rem;
    }

    .ef-announcements-body .announcement-message hr {
        margin: 1rem 0;
        border-color: var(--darkgray, #3d3a44);
        opacity: 0.5;
    }

    /* Pulse Animation für Trigger Button bei kritischen Meldungen */
    #efAnnouncementsTrigger .ignis-btn--danger {
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
        const body = document.getElementById('efAnnouncementsBody');
        const trigger = document.getElementById('efAnnouncementsTrigger');

        if (!body) return;

        const autoShow = body.dataset.autoShow === 'true';
        const allAnnouncementIds = <?= json_encode($allAnnouncementIds) ?>;

        let currentDialog = null;

        function remainingCount() {
            return body.querySelectorAll('.announcement-item').length;
        }

        function updateTrigger() {
            if (!trigger) return;
            trigger.style.display = remainingCount() > 0 ? 'block' : 'none';
            const countEl = trigger.querySelector('.ef-announce-fab__count');
            if (countEl) countEl.textContent = remainingCount();
        }

        function closeDialog() {
            if (currentDialog) currentDialog.close();
        }

        function openDialog() {
            if (remainingCount() === 0) return;
            body.hidden = false;
            currentDialog = new window.Dialog({
                title: 'Offizielle Ankündigungen von EmergencyForge',
                body: body,
                size: 'lg',
                preserveBody: true,
                actions: [{
                    label: 'Verstanden',
                    variant: 'primary',
                    primary: true,
                    close: false,
                    onClick: (dlg) => {
                        const btn = dlg.element.querySelector('[data-dialog-action="0"]');
                        if (!btn || btn.disabled) return;
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Wird gespeichert...';
                        Promise.all(allAnnouncementIds.map(id =>
                                fetch('<?= BASE_PATH ?>api/announcements/dismiss', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ announcement_id: id })
                                })
                            ))
                            .then(() => {
                                body.querySelectorAll('.announcement-item').forEach(el => el.remove());
                                dlg.close();
                                updateTrigger();
                            })
                            .catch(err => {
                                console.error('Dismiss all failed:', err);
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Verstanden';
                            });
                    },
                }],
                onClose: () => {
                    body.hidden = true;
                    currentDialog = null;
                    updateTrigger();
                },
            });
            currentDialog.open();
        }

        // Auto-show, wenn noch nicht in dieser Session gezeigt
        if (autoShow) {
            setTimeout(openDialog, 500);
        } else {
            updateTrigger();
        }

        if (trigger) {
            trigger.querySelector('button')?.addEventListener('click', openDialog);
        }

        // Einzelne Announcement ausblenden
        body.querySelectorAll('.dismiss-single-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const announcementId = this.dataset.announcementId;
                const item = this.closest('.announcement-item');

                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                fetch('<?= BASE_PATH ?>api/announcements/dismiss', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ announcement_id: announcementId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            item.style.transition = 'all 0.3s ease';
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(20px)';

                            setTimeout(() => {
                                item.remove();
                                if (remainingCount() === 0) {
                                    closeDialog();
                                }
                                updateTrigger();
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
            fetch('<?= BASE_PATH ?>api/telemetry/background?action=heartbeat').catch(function() {});
        <?php endif; ?>
        <?php if ($needsCacheRefresh): ?>
            fetch('<?= BASE_PATH ?>api/telemetry/background?action=refresh-announcements').catch(function() {});
        <?php endif; ?>
    });
</script>