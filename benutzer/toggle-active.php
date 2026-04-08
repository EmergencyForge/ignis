<?php

/**
 * Stub für GET /benutzer/toggle-active.php?id=X&action=deactivate|reactivate
 *
 * Phase 2.1: Modul migriert auf UserController.
 * Logik: src/Http/Controllers/UserController.php::setActive()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\UserController::class)->setActive();
