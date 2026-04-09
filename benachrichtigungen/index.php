<?php

/**
 * Stub für GET/POST /benachrichtigungen/index.php
 *
 * Phase 2 Welle 4: Modul migriert auf NotificationController.
 *
 * Routing nach POST-Action:
 *   GET                       → index()         (Liste mit Filter + Pagination)
 *   POST action=mark_read     → markAsRead()    (einzelne als gelesen)
 *   POST action=mark_all_read → markAllAsRead() (alle als gelesen)
 *   POST action=delete        → delete()        (einzelne löschen)
 *
 * Der zweite "Stub" benachrichtigungen/mark-read.php zeigt auf eine API
 * unter api/notifications/mark-read.php und ist nicht Teil dieser Migration —
 * er bleibt unverändert.
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\NotificationController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $controller->markAsRead();
    } elseif ($action === 'mark_all_read') {
        $controller->markAllAsRead();
    } elseif ($action === 'delete') {
        $controller->delete();
    } else {
        $controller->index();
    }
} else {
    $controller->index();
}
