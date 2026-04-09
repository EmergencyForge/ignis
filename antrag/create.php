<?php

/**
 * Stub für GET/POST /antrag/create.php?typ=X
 *
 * GET → AntragController::create() (Form anzeigen)
 * POST mit `submit_antrag` → AntragController::store() (Antrag speichern)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\AntragController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_antrag'])) {
    $controller->store();
} else {
    $controller->create();
}
