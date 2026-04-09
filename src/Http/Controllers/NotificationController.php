<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Flash;
use App\Notifications\NotificationManager;

/**
 * NotificationController — Migration des `benachrichtigungen/`-Moduls.
 *
 * Das Modul ist klein (2 Files, eine Liste + ein Stub auf api/notifications/),
 * weil die DB-Logik schon im NotificationManager-Service kapselt ist.
 * Wir müssen nur die View extrahieren und die 3 POST-Actions in Methoden
 * verwandeln.
 *
 * URL-Mapping:
 *   GET  /benachrichtigungen/index.php          → index()       (Liste)
 *   POST /benachrichtigungen/index.php (mark_read)     → markAsRead()
 *   POST /benachrichtigungen/index.php (mark_all_read) → markAllAsRead()
 *   POST /benachrichtigungen/index.php (delete)        → delete()
 */
class NotificationController extends Controller
{
    private const PAGE_SIZE = 20;

    private const VALID_TYPES = ['antrag', 'protokoll', 'dokument', 'system', 'fire_protocol'];

    /**
     * GET /benachrichtigungen/index.php — Liste der eigenen Benachrichtigungen,
     * mit Filter (alle/ungelesen + Type-Filter) und Pagination via offset.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('notification.viewAny', redirectTo: 'index.php');

        $manager = new NotificationManager($this->pdo);
        $userId  = (int) $_SESSION['userid'];

        $filter     = (string) ($_GET['filter'] ?? 'all');
        $typeFilter = $_GET['type'] ?? null;
        if ($typeFilter !== null && !in_array($typeFilter, self::VALID_TYPES, true)) {
            $typeFilter = null;
        }

        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        // Wir laden eine Page+1, um zu erkennen ob "Mehr laden" angezeigt werden soll.
        if ($filter === 'unread') {
            $notifications = $manager->getUnread($userId, self::PAGE_SIZE + 1, $typeFilter, $offset);
        } else {
            $notifications = $manager->getAll($userId, self::PAGE_SIZE + 1, $offset, $typeFilter);
        }

        $hasMore = count($notifications) > self::PAGE_SIZE;
        if ($hasMore) {
            array_pop($notifications);
        }

        $unreadCount = $manager->getUnreadCount($userId);

        $this->renderView('benachrichtigungen/index', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'filter'        => $filter,
            'typeFilter'    => $typeFilter,
            'offset'        => $offset,
            'pageSize'      => self::PAGE_SIZE,
            'hasMore'       => $hasMore,
        ]);
    }

    /**
     * POST mit action=mark_read — eine einzelne Benachrichtigung als gelesen markieren.
     */
    public function markAsRead(): void
    {
        $this->requireAuth();
        $this->ensure('notification.markRead', redirectTo: 'index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            (new NotificationManager($this->pdo))->markAsRead($id, (int) $_SESSION['userid']);
            Flash::set('success', 'Benachrichtigung als gelesen markiert');
        }

        $this->redirect('benachrichtigungen/index.php');
    }

    /**
     * POST mit action=mark_all_read — ALLE Benachrichtigungen als gelesen markieren.
     */
    public function markAllAsRead(): void
    {
        $this->requireAuth();
        $this->ensure('notification.markRead', redirectTo: 'index.php');

        (new NotificationManager($this->pdo))->markAllAsRead((int) $_SESSION['userid']);
        Flash::set('success', 'Alle Benachrichtigungen als gelesen markiert');

        $this->redirect('benachrichtigungen/index.php');
    }

    /**
     * POST mit action=delete — eine einzelne Benachrichtigung löschen.
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->ensure('notification.delete', redirectTo: 'index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            (new NotificationManager($this->pdo))->delete($id, (int) $_SESSION['userid']);
            Flash::set('success', 'Benachrichtigung gelöscht');
        }

        $this->redirect('benachrichtigungen/index.php');
    }
}
