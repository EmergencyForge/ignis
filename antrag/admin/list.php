<?php

/**
 * Stub für GET /antrag/admin/list.php
 *
 * Phase 2 Welle 2: Modul migriert auf AntragController.
 * Logik: src/Http/Controllers/AntragController.php::adminList()
 */

require_once __DIR__ . '/../../assets/config/config.php';

app(\App\Http\Controllers\AntragController::class)->adminList();
