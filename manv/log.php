<?php

/**
 * Stub für GET /manv/log.php?id=X
 *
 * Logik: src/Http/Controllers/ManvController.php::log()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\ManvController::class)->log();
