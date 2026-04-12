<?php

/**
 * Stub für GET /enotf/print/index.php?enr=X
 *
 * Logik: src/Http/Controllers/EnotfPrintController.php::show()
 *
 * Cookie-Settings für CitizenFX MÜSSEN vor session_start() gesetzt werden.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/../../assets/config/config.php';

app(\App\Http\Controllers\EnotfPrintController::class)->show();
