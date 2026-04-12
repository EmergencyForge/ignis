<?php

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../../assets/config/config.php';

app(\App\Http\Controllers\EnotfSchnittstelleController::class)->hospitalAvailability();
