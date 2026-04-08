<?php

/**
 * Stub für GET /benutzer/list.php
 *
 * Phase 2.1: Modul migriert auf UserController + Eloquent-Models.
 * Dieser Stub ist temporär bis Phase 3 (zentraler Router) eingeführt wird.
 *
 * Verantwortlichkeiten der bisherigen list.php sind jetzt in:
 *   - src/Http/Controllers/UserController.php::index()
 *   - templates/users/list.php
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\UserController::class)->index();
