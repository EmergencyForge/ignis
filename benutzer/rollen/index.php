<?php

/**
 * Stub für GET /benutzer/rollen/index.php
 *
 * Phase 2.1: Modul migriert auf RoleController.
 * Logik: src/Http/Controllers/RoleController.php::index()
 */

require_once __DIR__ . '/../../assets/config/config.php';

app(\App\Http\Controllers\RoleController::class)->index();
