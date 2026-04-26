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

    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <h1>Benachrichtigungen</h1>

            <?php Flash::render(); ?>

            <div class="my-3 flex items-center justify-between">
                <div class="flex flex-wrap items-center gap-1">
                    <a href="<?= notifFilterUrl(['filter' => 'all', 'type' => null]) ?>" class="ignis-btn ignis-btn--sm no-underline hover:no-underline <?= $filter === 'all' && !$typeFilter ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                        Alle
                    </a>
                    <a href="<?= notifFilterUrl(['filter' => 'unread', 'type' => null]) ?>" class="ignis-btn ignis-btn--sm no-underline hover:no-underline <?= $filter === 'unread' && !$typeFilter ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                        Ungelesen (<?= (int) $unreadCount ?>)
                    </a>
                    <span class="mx-2 inline-block h-5 align-middle" style="border-left: 1px solid var(--border-color);"></span>
                    <?php foreach ($typeLabels as $typeKey => $typeInfo): ?>
                        <a href="<?= notifFilterUrl(['type' => $typeKey]) ?>" class="ignis-btn ignis-btn--sm no-underline hover:no-underline <?= $typeFilter === $typeKey ? 'btn-soft-primary' : 'btn-outline-secondary' ?>" title="<?= $typeInfo['label'] ?>">
                            <i class="fa-solid <?= $typeInfo['icon'] ?> mr-1"></i><?= $typeInfo['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="ignis-btn ignis-btn--sm btn-outline-light">
                            <i class="fa-solid fa-check mr-1"></i> Alle als gelesen markieren
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="intra__tile mb-6 p-0">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-gray-400">
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
                            <div class="notification-item border-b border-white/10 p-3">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0">
                                        <div class="notification-icon <?= htmlspecialchars($groupType) ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <details>
                                            <summary class="cursor-pointer" style="list-style: none;">
                                                <h6 class="mb-0 inline">
                                                    <i class="fa-solid fa-layer-group mr-1" style="font-size: 0.75rem; opacity: 0.5;"></i>
                                                    <?= count($group['items']) ?> <?= htmlspecialchars($groupTypeLabel) ?>
                                                </h6>
                                                <small class="notification-date ml-2">
                                                    <i class="fa-solid fa-clock mr-1"></i><?= notifFormatTimeAgo($group['items'][0]['created_at']) ?>
                                                </small>
                                            </summary>
                                            <div class="mt-2 ml-1 border-l-2 border-white/10 pl-3">
                                            <?php foreach ($group['items'] as $notification):
                                                $isUnread = $notification['is_read'] == 0;
                                            ?>
                                                <div class="flex items-start py-2 text-sm <?= $isUnread ? 'font-semibold' : '' ?>">
                                                    <div class="flex-1">
                                                        <?= htmlspecialchars($notification['title']) ?>
                                                        <?php if ($notification['message']): ?>
                                                            <span class="text-gray-400"> — <?= htmlspecialchars($notification['message']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="notification-actions ml-2" style="opacity: 1;">
                                                        <?php if ($notification['link']): ?>
                                                            <a href="<?= htmlspecialchars($notification['link']) ?>"
                                                                class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon notification-open-link no-underline hover:no-underline"
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
                            <div class="notification-item border-b border-white/10 p-3 <?= $isUnread ? 'unread' : '' ?>">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0">
                                        <div class="notification-icon <?= htmlspecialchars($notification['type']) ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <h6 class="mb-1 <?= $isUnread ? 'font-bold' : '' ?>">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                </h6>
                                                <?php if ($notification['message']): ?>
                                                    <p class="mb-1 break-words text-gray-400">
                                                        <?= htmlspecialchars($notification['message']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <small class="notification-date">
                                                    <i class="fa-solid fa-clock mr-1"></i>
                                                    <?= $timeAgo ?>
                                                </small>
                                            </div>
                                            <div class="notification-actions shrink-0">
                                                <?php if ($notification['link']): ?>
                                                    <a href="<?= htmlspecialchars($notification['link']) ?>"
                                                        class="ignis-btn ignis-btn--sm ignis-btn--soft-primary mr-1 notification-open-link no-underline hover:no-underline"
                                                        title="Öffnen"
                                                        <?= $isUnread ? 'data-notification-id="' . (int) $notification['id'] . '"' : '' ?>>
                                                        <i class="fa-solid fa-external-link-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($isUnread): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary ignis-btn--icon mr-1" title="Als gelesen markieren">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="inline" onsubmit="event.preventDefault(); showConfirm('Benachrichtigung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Benachrichtigung löschen'}).then(result => { if(result) this.submit(); });">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                                    <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon" title="Löschen">
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
                <div class="my-3 text-center">
                    <a href="<?= notifFilterUrl(['offset' => $offset + $pageSize]) ?>" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary no-underline hover:no-underline">
                        <i class="fa-solid fa-chevron-down mr-1"></i>Mehr laden
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

    <script>
    document.querySelectorAll('.notification-open-link[data-notification-id]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var href = this.href;
            var notifId = this.dataset.notificationId;
            fetch('<?= BASE_PATH ?>api/notifications/mark-read', {
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
