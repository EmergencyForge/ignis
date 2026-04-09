<?php
/**
 * View: Benachrichtigungen-Liste
 *
 * @var array<int,array<string,mixed>> $notifications  Array von Notification-Rows aus NotificationManager
 * @var int          $unreadCount
 * @var string       $filter           'all'|'unread'
 * @var string|null  $typeFilter
 * @var int          $offset
 * @var int          $pageSize
 * @var bool         $hasMore
 * @var \PDO         $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Benachrichtigungen';

/**
 * Build query string preserving other params.
 *
 * @param array<string, string|null> $overrides
 */
function notifFilterUrl(array $overrides): string
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return '?' . http_build_query($params);
}

/**
 * Format a created_at timestamp as a German "vor X Minuten" string.
 */
function notifFormatTimeAgo(string $createdAt): string
{
    $datetime = new DateTime($createdAt, new DateTimeZone('Europe/Berlin'));
    $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $now  = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
    $diff = $now->diff($datetime);

    if ($diff->invert == 0) {
        return 'Gerade eben';
    }
    if ($diff->days > 0) {
        return 'Vor ' . $diff->days . ' Tag' . ($diff->days > 1 ? 'en' : '');
    }
    if ($diff->h > 0) {
        return 'Vor ' . $diff->h . ' Stunde' . ($diff->h > 1 ? 'n' : '');
    }
    if ($diff->i > 0) {
        return 'Vor ' . $diff->i . ' Minute' . ($diff->i > 1 ? 'n' : '');
    }
    return 'Gerade eben';
}

$typeLabels = [
    'antrag'        => ['label' => 'Anträge',     'icon' => 'fa-file'],
    'protokoll'     => ['label' => 'Protokolle',  'icon' => 'fa-truck-medical'],
    'dokument'      => ['label' => 'Dokumente',   'icon' => 'fa-folder-open'],
    'fire_protocol' => ['label' => 'Einsätze',    'icon' => 'fa-fire'],
    'system'        => ['label' => 'System',      'icon' => 'fa-gears'],
];

$iconClass = [
    'antrag'        => 'fa-file',
    'protokoll'     => 'fa-truck-medical',
    'dokument'      => 'fa-folder-open',
    'fire_protocol' => 'fa-fire',
    'system'        => 'fa-gears',
];

// Group adjacent notifications of the same type within 5 minutes
$groups = [];
foreach ($notifications as $n) {
    $lastGroup = end($groups);
    if ($lastGroup && $lastGroup['type'] === $n['type']) {
        $lastTime = new DateTime(end($lastGroup['items'])['created_at']);
        $thisTime = new DateTime($n['created_at']);
        $diffSec  = abs($lastTime->getTimestamp() - $thisTime->getTimestamp());
        if ($diffSec <= 300) {
            $groups[array_key_last($groups)]['items'][] = $n;
            continue;
        }
    }
    $groups[] = ['type' => $n['type'], 'items' => [$n]];
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
    <style>
        .notification-item {
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }

        .notification-item.unread {
            border-left-color: var(--main-color);
        }

        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 1.2rem;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
        }

        .notification-date {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .notification-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .notification-item:hover .notification-actions {
            opacity: 1;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="benachrichtigungen">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1>Benachrichtigungen</h1>

                    <?php Flash::render(); ?>

                    <div class="my-3 d-flex justify-content-between align-items-center">
                        <div>
                            <a href="<?= notifFilterUrl(['filter' => 'all', 'type' => null]) ?>" class="btn btn-sm <?= $filter === 'all' && !$typeFilter ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                                Alle
                            </a>
                            <a href="<?= notifFilterUrl(['filter' => 'unread', 'type' => null]) ?>" class="btn btn-sm <?= $filter === 'unread' && !$typeFilter ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                                Ungelesen (<?= (int) $unreadCount ?>)
                            </a>
                            <span class="mx-2" style="border-left: 1px solid var(--border-color); height: 20px; display: inline-block; vertical-align: middle;"></span>
                            <?php foreach ($typeLabels as $typeKey => $typeInfo): ?>
                                <a href="<?= notifFilterUrl(['type' => $typeKey]) ?>" class="btn btn-sm <?= $typeFilter === $typeKey ? 'btn-soft-primary' : 'btn-outline-secondary' ?>" title="<?= $typeInfo['label'] ?>">
                                    <i class="fa-solid <?= $typeInfo['icon'] ?> me-1"></i><?= $typeInfo['label'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-sm btn-outline-light">
                                    <i class="fa-solid fa-check me-1"></i> Alle als gelesen markieren
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="intra__tile p-0 mb-5">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                                <p class="mb-0">Keine Benachrichtigungen vorhanden</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($groups as $gi => $group):
                                $isGrouped = count($group['items']) > 1;
                                $groupType = $group['type'];
                                $icon      = $iconClass[$groupType] ?? 'fa-bell';
                                $groupTypeLabel = $typeLabels[$groupType]['label'] ?? $groupType;

                                if ($isGrouped): ?>
                                    <div class="notification-item p-3 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="notification-icon <?= htmlspecialchars($groupType) ?>">
                                                    <i class="fa-solid <?= $icon ?>"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <details>
                                                    <summary style="cursor: pointer; list-style: none;">
                                                        <h6 class="mb-0 d-inline">
                                                            <i class="fa-solid fa-layer-group me-1" style="font-size: 0.75rem; opacity: 0.5;"></i>
                                                            <?= count($group['items']) ?> <?= htmlspecialchars($groupTypeLabel) ?>
                                                        </h6>
                                                        <small class="notification-date ms-2">
                                                            <i class="fa-solid fa-clock me-1"></i><?= notifFormatTimeAgo($group['items'][0]['created_at']) ?>
                                                        </small>
                                                    </summary>
                                                    <div class="mt-2" style="border-left: 2px solid var(--border-color); margin-left: 4px; padding-left: 12px;">
                                                    <?php foreach ($group['items'] as $notification):
                                                        $isUnread = $notification['is_read'] == 0;
                                                    ?>
                                                        <div class="d-flex align-items-start py-2 <?= $isUnread ? 'fw-semibold' : '' ?>" style="font-size: var(--font-size-sm);">
                                                            <div class="flex-grow-1">
                                                                <?= htmlspecialchars($notification['title']) ?>
                                                                <?php if ($notification['message']): ?>
                                                                    <span class="text-muted"> — <?= htmlspecialchars($notification['message']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="notification-actions ms-2" style="opacity: 1;">
                                                                <?php if ($notification['link']): ?>
                                                                    <a href="<?= htmlspecialchars($notification['link']) ?>"
                                                                        class="btn btn-sm btn-soft-primary btn-icon notification-open-link"
                                                                        title="Öffnen"
                                                                        <?= $isUnread ? 'data-notification-id="' . (int) $notification['id'] . '"' : '' ?>>
                                                                        <i class="fa-solid fa-external-link-alt"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                <?php else:
                                    $notification = $group['items'][0];
                                    $isUnread     = $notification['is_read'] == 0;
                                    $timeAgo      = notifFormatTimeAgo($notification['created_at']);
                                ?>
                                    <div class="notification-item <?= $isUnread ? 'unread' : '' ?> p-3 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="notification-icon <?= htmlspecialchars($notification['type']) ?>">
                                                    <i class="fa-solid <?= $icon ?>"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 <?= $isUnread ? 'fw-bold' : '' ?>">
                                                            <?= htmlspecialchars($notification['title']) ?>
                                                        </h6>
                                                        <?php if ($notification['message']): ?>
                                                            <p class="mb-1 text-muted" style="word-wrap: break-word; overflow-wrap: break-word;">
                                                                <?= htmlspecialchars($notification['message']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <small class="notification-date">
                                                            <i class="fa-solid fa-clock me-1"></i>
                                                            <?= $timeAgo ?>
                                                        </small>
                                                    </div>
                                                    <div class="notification-actions ms-3">
                                                        <?php if ($notification['link']): ?>
                                                            <a href="<?= htmlspecialchars($notification['link']) ?>"
                                                                class="btn btn-sm btn-soft-primary me-1 notification-open-link"
                                                                title="Öffnen"
                                                                <?= $isUnread ? 'data-notification-id="' . (int) $notification['id'] . '"' : '' ?>>
                                                                <i class="fa-solid fa-external-link-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($isUnread): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_read">
                                                                <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary btn-icon me-1" title="Als gelesen markieren">
                                                                    <i class="fa-solid fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); showConfirm('Benachrichtigung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Benachrichtigung löschen'}).then(result => { if(result) this.submit(); });">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Löschen">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasMore): ?>
                        <div class="text-center my-3">
                            <a href="<?= notifFilterUrl(['offset' => $offset + $pageSize]) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-chevron-down me-1"></i>Mehr laden
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

    <script>
    document.querySelectorAll('.notification-open-link[data-notification-id]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var href = this.href;
            var notifId = this.dataset.notificationId;
            fetch('<?= BASE_PATH ?>api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(notifId) })
            }).finally(function() {
                window.location.href = href;
            });
        });
    });
    </script>
</body>

</html>
