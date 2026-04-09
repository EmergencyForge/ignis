<?php

/**
 * Stub für GET /antrag/view.php?antrag=X
 *
 * Phase 2 Welle 2: Modul migriert auf AntragController.
 * Logik: src/Http/Controllers/AntragController.php::view()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\AntragController::class)->view();
