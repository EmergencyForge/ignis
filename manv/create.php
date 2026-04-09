<?php

/**
 * Stub für GET/POST /manv/create.php
 *
 * Phase 2 Welle 6 Turn 1: Modul migriert auf ManvController.
 * GET  → ManvController::create() (Form)
 * POST → ManvController::store()  (Lage anlegen + Audit-Log + redirect)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\ManvController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->store();
} else {
    $controller->create();
}
