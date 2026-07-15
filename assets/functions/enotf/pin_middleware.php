<?php

use App\Auth\Permissions;
use Plugin\Enotf\Helpers\EnotfUrl;

// Session wird durch config.php gestartet (SessionManager)
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/config.php';
}

if (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) {

    // Benutzer mit admin oder edivi.view Berechtigung sind vom Lockscreen ausgenommen
    // Permissions::check() prüft automatisch auch auf 'full_admin'
    $is_exempt_user = Permissions::check(['edivi.view']);

    // Prüfe ob Klinikzugriff aktiv ist (innerhalb von 2 Stunden nach Code-Eingabe)
    $is_klinik_access = false;
    if (isset($_SESSION['klinik_access_enr']) && isset($_SESSION['klinik_access_time'])) {
        $access_time = $_SESSION['klinik_access_time'];
        $current_time = time();
        // Klinikzugriff gilt für 2 Stunden
        if (($current_time - $access_time) < 7200) {
            $is_klinik_access = true;
        } else {
            // Zugriff abgelaufen, Session-Variablen löschen
            unset($_SESSION['klinik_access_enr']);
            unset($_SESSION['klinik_access_time']);
        }
    }

    // Wenn Benutzer ausgenommen ist oder Klinikzugriff aktiv, Lockscreen-Logik überspringen
    if (!$is_exempt_user && !$is_klinik_access) {
        $current_time = time();
        $timeout = 300; // 5 Minuten = 300 Sekunden

        $pin_verified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;

        $last_activity = $_SESSION['pin_last_activity'] ?? null;

        $is_timeout = ($last_activity === null || ($current_time - $last_activity) > $timeout);

        if (!$pin_verified || $is_timeout) {
            if (basename($_SERVER['PHP_SELF']) !== 'lockscreen.php') {
                $_SESSION['pin_return_url'] = $_SERVER['REQUEST_URI'];
            }

            $_SESSION['pin_verified'] = false;
            unset($_SESSION['pin_last_activity']);

            header("Location: " . EnotfUrl::page('lockscreen'));
            exit();
        }

        $_SESSION['pin_last_activity'] = $current_time;
    }
}
