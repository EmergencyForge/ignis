<?php

/**
 * Stub für GET/POST /einsatz/login-fahrzeug.php
 *
 * Phase 2 Welle 7 Turn 1: Modul migriert auf EinsatzController.
 *   GET                → loginForm() (Form anzeigen oder Logout)
 *   GET ?logout=1      → loginForm() (Logout-Action wird intern erkannt)
 *   POST               → login()     (Fahrzeug-Login durchführen)
 *
 * Cookie-Settings für CitizenFX MÜSSEN vor session_start() gesetzt werden.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\EinsatzController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->login();
} else {
    $controller->loginForm();
}
