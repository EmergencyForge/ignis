<?php

/**
 * Stub für GET/POST /einsatz/create.php
 *
 * Logik: src/Http/Controllers/EinsatzController.php::createForm() / store()
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
    $controller->store();
} else {
    $controller->createForm();
}
