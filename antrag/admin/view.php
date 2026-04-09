<?php

/**
 * Stub für GET/POST /antrag/admin/view.php?antrag=X
 *
 * GET → AntragController::adminView() (Detail-Form)
 * POST mit `save` → AntragController::decide() (Status setzen + Notification)
 */

require_once __DIR__ . '/../../assets/config/config.php';

$controller = app(\App\Http\Controllers\AntragController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $controller->decide();
} else {
    $controller->adminView();
}
