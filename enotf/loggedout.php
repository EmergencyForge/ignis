<?php

/**
 * Stub für GET /enotf/loggedout.php
 *
 * Logik: src/Http/Controllers/EnotfController.php::logout()
 *
 * ACHTUNG: Diese Route macht DB-Writes auf GET (Legacy-Verhalten):
 *   ?mode=self → eigene Position aus der Crew-Session entfernen
 *   ?mode=all  → komplette Fahrzeug-Session deaktivieren
 *
 * Cookie-Settings für CitizenFX MÜSSEN vor session_start() gesetzt werden.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\EnotfController::class)->logout();
