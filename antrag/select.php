<?php

/**
 * Stub für GET /antrag/select.php
 *
 * Phase 2 Welle 2: Modul wird inkrementell auf AntragController migriert.
 * Logik: src/Http/Controllers/AntragController.php::selectType()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\AntragController::class)->selectType();
