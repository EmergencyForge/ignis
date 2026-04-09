<?php

/**
 * Stub für GET/POST /manv/edit.php?id=X
 *
 * Phase 2 Welle 6 Turn 1: Modul migriert auf ManvController.
 * GET  → ManvController::edit()   (Edit-Form)
 * POST → ManvController::update() (Lage aktualisieren)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\ManvController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->update();
} else {
    $controller->edit();
}
