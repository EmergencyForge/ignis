<?php

/**
 * Stub für GET/POST /manv/create.php
 *
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
