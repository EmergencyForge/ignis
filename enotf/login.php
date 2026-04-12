<?php

/**
 * Stub für GET/POST /enotf/login.php
 *
 * Logik: src/Http/Controllers/EnotfController.php::loginForm() / login()
 *
 * Cookie-Settings für CitizenFX MÜSSEN vor session_start() gesetzt werden.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\EnotfController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->login();
} else {
    $controller->loginForm();
}
