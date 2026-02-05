<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../assets/config/config.php';

if (isset($_SESSION['einsatz_vehicle_name']) && isset($_SESSION['einsatz_operator_name'])) {
    header("Location: " . BASE_PATH . "einsatz/list.php");
    exit();
} else {
    header("Location: " . BASE_PATH . "einsatz/login-fahrzeug.php");
    exit();
}
