<?php

/**
 * Stub für POST /benutzer/rollen/create.php
 *
 * Phase 2.1: Modul migriert auf RoleController.
 * Logik: src/Http/Controllers/RoleController.php::store()
 */

require_once __DIR__ . '/../../assets/config/config.php';

app(\App\Http\Controllers\RoleController::class)->store();
