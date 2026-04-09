<?php

/**
 * Stub für GET /fahrtenbuch/index.php
 *
 * Phase 2 Welle 5: Modul migriert auf FahrtenbuchController.
 * Logik: src/Http/Controllers/FahrtenbuchController.php::index()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\FahrtenbuchController::class)->index();
