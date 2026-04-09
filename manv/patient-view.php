<?php

/**
 * Stub für GET/POST /manv/patient-view.php?id=X
 *
 * GET  → patientView()   (Detail-Form, inkl. quick_sk Quick-Sichtung)
 * POST → patientUpdate() (Patient aktualisieren)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\ManvController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->patientUpdate();
} else {
    $controller->patientView();
}
