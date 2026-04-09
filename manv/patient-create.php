<?php

/**
 * Stub für GET/POST /manv/patient-create.php?lage_id=X
 *
 * Phase 2 Welle 6 Turn 2: Modul migriert auf ManvController.
 * GET  → patientCreate() (Form)
 * POST → patientStore()  (Patient anlegen)
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\ManvController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->patientStore();
} else {
    $controller->patientCreate();
}
