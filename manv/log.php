<?php

/**
 * Stub für GET /manv/log.php?id=X
 *
 * Phase 2 Welle 6 Turn 1: Modul migriert auf ManvController.
 * Logik: src/Http/Controllers/ManvController.php::log()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\ManvController::class)->log();
