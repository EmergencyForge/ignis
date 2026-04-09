<?php

/**
 * Stub für GET /mitarbeiter/delete.php?id=X
 *
 * Logik: src/Http/Controllers/MitarbeiterController.php::destroy()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->destroy();
