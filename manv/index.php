<?php

/**
 * Stub für GET /manv/index.php
 *
 * Phase 2 Welle 6 Turn 1: Modul wird inkrementell auf ManvController migriert.
 * Logik: src/Http/Controllers/ManvController.php::index()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\ManvController::class)->index();
