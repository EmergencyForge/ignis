<?php
// Session wird durch config.php gestartet (SessionManager)
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/config.php';
}

use Plugin\Enotf\Helpers\EnotfUrl;

if (defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true) {

    $user_authenticated = isset($_SESSION['userid']) && !empty($_SESSION['userid']);

    // Prüfe ob Klinikzugriff aktiv ist
    $is_klinik_access = false;
    if (isset($_SESSION['klinik_access_enr']) && isset($_SESSION['klinik_access_time'])) {
        $access_time = $_SESSION['klinik_access_time'];
        $current_time = time();
        // Klinikzugriff gilt für 2 Stunden
        if (($current_time - $access_time) < 7200) {
            $is_klinik_access = true;
        }
    }

    // Wenn nicht authentifiziert UND kein Klinikzugriff
    if (!$user_authenticated && !$is_klinik_access) {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';

        // Only set redirect URL if not already on login or loggedout pages
        // Use strict comparison to prevent false positives when path starts at position 0
        if (
            strpos($script_name, '/enotf/login.php') === false &&
            strpos($script_name, '/enotf/loggedout.php') === false
        ) {
            // After authentication, user should go to eNOTF login to enter crew info
            // Using BASE_PATH constant to ensure safe redirect within application
            $_SESSION['redirect_url'] = EnotfUrl::page('login');
        }

        header("Location: " . BASE_PATH . "login.php?redirect=enotf");
        exit();
    }
}
