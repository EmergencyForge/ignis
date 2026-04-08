<?php

/**
 * Stub für GET /benutzer/auditlog.php
 *
 * Phase 2.1: Modul migriert auf UserController.
 * Logik: src/Http/Controllers/UserController.php::auditlog()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\UserController::class)->auditlog();
