<?php

/**
 * Stub für GET /einsatz/index.php
 *
 * Phase 2 Welle 7 Turn 1: Modul migriert auf EinsatzController.
 * Logik: src/Http/Controllers/EinsatzController.php::index()
 *
 * WICHTIG: Cookie-Settings für CitizenFX-In-Game-Browser MÜSSEN VOR
 * session_start() (im Bootstrap) gesetzt werden — daher hier inline,
 * nicht erst im Controller.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\EinsatzController::class)->index();
