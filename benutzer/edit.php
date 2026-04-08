<?php

/**
 * Stub für GET/POST /benutzer/edit.php?id=X
 *
 * Phase 2.1: Modul migriert auf UserController.
 * GET → UserController::edit() (Form anzeigen)
 * POST mit `new=1` → UserController::update() (Rolle ändern)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\UserController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['new'] ?? '') === '1')) {
    $controller->update();
} else {
    $controller->edit();
}
