<?php

/**
 * Globale Announcements Banner-Komponente
 * 
 * Einbinden: <?php include __DIR__ . '/assets/components/global-announcements.php'; ?>
 */

require_once __DIR__ . '/../../src/Telemetry/GlobalAnnouncementManager.php';

use App\Telemetry\GlobalAnnouncementManager;

if (!isset($_SESSION['userid'])) {
    return;
}

try {
    require_once __DIR__ . '/../config/database.php';

    $announcementManager = new GlobalAnnouncementManager($pdo);
    $announcements = $announcementManager->getActiveAnnouncements($_SESSION['userid']);

    if (empty($announcements)) {
        return;
    }
} catch (Exception $e) {
    error_log("Global announcements error: " . $e->getMessage());
    return;
}
?>

<div class="global-announcements-container mb-3">
    <?php foreach ($announcements as $ann):
        $alertClass = GlobalAnnouncementManager::getAlertClass($ann['type']);
        $icon = GlobalAnnouncementManager::getIcon($ann['type']);
    ?>
        <div class="alert <?= $alertClass ?> alert-dismissible fade show d-flex align-items-center"
            role="alert" data-announcement-id="<?= htmlspecialchars($ann['announcement_id']) ?>">
            <i class="fa-solid <?= $icon ?> me-2"></i>
            <div class="flex-grow-1">
                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                <?php if (!empty($ann['message'])): ?>
                    <p class="mb-0 small"><?= htmlspecialchars($ann['message']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ann['link'])): ?>
                    <a href="<?= htmlspecialchars($ann['link']) ?>" class="alert-link" target="_blank">
                        Mehr erfahren <i class="fa-solid fa-external-link-alt fa-xs"></i>
                    </a>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Ausblenden"
                onclick="dismissAnnouncement('<?= htmlspecialchars($ann['announcement_id']) ?>')"></button>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function dismissAnnouncement(announcementId) {
        fetch('<?= BASE_PATH ?>api/dismiss-announcement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                announcement_id: announcementId
            })
        }).catch(err => console.error('Dismiss failed:', err));
    }
</script>