<?php

/**
 * Stub für GET/POST /benutzer/registration-codes.php
 *
 * Phase 2.1: Modul migriert auf UserController.
 * Internes Dispatching nach REQUEST_METHOD + action erfolgt im Controller.
 * Logik: src/Http/Controllers/UserController.php::registrationCodes()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\UserController::class)->registrationCodes();
