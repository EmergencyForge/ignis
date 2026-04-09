<?php

/**
 * Stub für POST /mitarbeiter/create.php (AJAX-Endpoint, JSON-Response)
 *
 * Logik: src/Http/Controllers/MitarbeiterController.php::store()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->store();
