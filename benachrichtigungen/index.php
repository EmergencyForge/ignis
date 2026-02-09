<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Helpers\Flash;
use App\Notifications\NotificationManager;

$notificationManager = new NotificationManager($pdo);
$userId = $_SESSION['userid'];

// Handle POST actions (state-changing operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify action exists
    $action = $_POST['action'] ?? null;

    if ($action === 'mark_read' && isset($_POST['id']) && is_numeric($_POST['id'])) {
        $notificationManager->markAsRead((int)$_POST['id'], $userId);
        Flash::set('success', 'Benachrichtigung als gelesen markiert');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }

    if ($action === 'mark_all_read') {
        $notificationManager->markAllAsRead($userId);
        Flash::set('success', 'Alle Benachrichtigungen als gelesen markiert');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }

    if ($action === 'delete' && isset($_POST['id']) && is_numeric($_POST['id'])) {
        $notificationManager->delete((int)$_POST['id'], $userId);
        Flash::set('success', 'Benachrichtigung gelöscht');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'unread') {
    $notifications = $notificationManager->getUnread($userId, 100);
} else {
    $notifications = $notificationManager->getAll($userId, 100);
}

$unreadCount = $notificationManager->getUnreadCount($userId);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <?php
    $SITE_TITLE = 'Benachrichtigungen';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
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
        }

        .notification-icon {
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
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1>
                        Benachrichtigungen
                    </h1>

                    <?php Flash::render(); ?>

                    <div class="my-3 d-flex justify-content-between align-items-center">
                        <div>
                            <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                                Alle
                            </a>
                            <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-soft-primary' : 'btn-outline-secondary' ?>">
                                Ungelesen (<?= $unreadCount ?>)
                            </a>
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
                            <?php foreach ($notifications as $notification):
                                $isUnread = $notification['is_read'] == 0;

                                // MySQL stores timestamps in its system timezone, we need to convert to PHP's timezone
                                // Create DateTime in MySQL's timezone (system timezone), then convert to PHP's timezone
                                $datetime = new DateTime($notification['created_at'], new DateTimeZone('Europe/Berlin')); // MySQL SYSTEM timezone
                                $datetime->setTimezone(new DateTimeZone(date_default_timezone_get())); // Convert to PHP timezone (UTC)
                                $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
                                $diff = $now->diff($datetime);

                                // Check if the notification is in the future (clock skew)
                                if ($diff->invert == 0) {
                                    // Notification is in the future, show as "Gerade eben"
                                    $timeAgo = 'Gerade eben';
                                } elseif ($diff->days > 0) {
                                    // more than 1 day ago = (en), also change formatting to Vor X Tagen

                                    $timeAgo = 'Vor ' . $diff->days . ' Tag' . ($diff->days > 1 ? 'en' : '');
                                } elseif ($diff->h > 0) {
                                    $timeAgo = 'Vor ' . $diff->h . ' Stunde' . ($diff->h > 1 ? 'n' : '');
                                } elseif ($diff->i > 0) {
                                    $timeAgo = 'Vor ' . $diff->i . ' Minute' . ($diff->i > 1 ? 'n' : '');
                                } else {
                                    $timeAgo = 'Gerade eben';
                                }

                                $iconClass = [
                                    'antrag' => 'fa-file',
                                    'protokoll' => 'fa-truck-medical',
                                    'dokument' => 'fa-folder-open',
                                    'fire_protocol' => 'fa-fire',
                                    'system' => 'fa-gears'
                                ];
                                $icon = $iconClass[$notification['type'] ?? ''] ?? 'fa-bell';
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
                                                            class="btn btn-sm btn-soft-primary me-1"
                                                            title="Öffnen">
                                                            <i class="fa-solid fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($isUnread): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="id" value="<?= $notification['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary btn-icon me-1" title="Als gelesen markieren">
                                                                <i class="fa-solid fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); showConfirm('Benachrichtigung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Benachrichtigung löschen'}).then(result => { if(result) this.submit(); });">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $notification['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Löschen">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>