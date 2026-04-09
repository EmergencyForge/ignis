<?php

/**
 * Stub für POST /mitarbeiter/create.php (AJAX-Endpoint, JSON-Response)
 *
 * Phase 2 Welle 3 Turn 1: Modul wird inkrementell auf MitarbeiterController migriert.
 * Logik: src/Http/Controllers/MitarbeiterController.php::store()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->store();
