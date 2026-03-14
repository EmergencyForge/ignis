<?php

// Autoloader muss zuerst geladen werden
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Permissions;
use App\Config\ConfigManager;
use App\Logging\ErrorHandler;
use App\Session\SessionManager;

// ============================================================================
// Globales Error-Handling & Logging registrieren
// ============================================================================
ErrorHandler::register();

// ============================================================================
// Session mit Sicherheitsoptimierungen starten
// ============================================================================
SessionManager::start();

// ============================================================================
// Permissions mit TTL (Time-to-Live)
// ============================================================================
// Permissions werden alle 5 Minuten neu aus der DB geladen.
// Das stellt sicher, dass Änderungen an Rollen zeitnah wirksam werden.
if (isset($_SESSION['userid'])) {
    $permissionsAge = time() - ($_SESSION['permissions_loaded'] ?? 0);
    $permissionsTTL = 300; // 5 Minuten
    
    if (!isset($_SESSION['permissions']) || $permissionsAge > $permissionsTTL) {
        require_once __DIR__ . '/database.php';
        $_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid']);
        $_SESSION['permissions_loaded'] = time();
    }
}

// Load configuration from database
require_once __DIR__ . '/database.php';

try {
    $configManager = new ConfigManager($pdo);
    $configManager->loadAndDefineConfig();
} catch (Exception $e) {
    // Fallback to default values if database is not available or table doesn't exist
    \App\Logging\Logger::warning("Could not load config from database: " . $e->getMessage());

    // BASIS DATEN - Fallback defaults
    if (!defined('API_KEY')) define('API_KEY', 'CHANGE_ME');
    if (!defined('SYSTEM_NAME')) define('SYSTEM_NAME', 'intraRP');
    if (!defined('SYSTEM_COLOR')) define('SYSTEM_COLOR', '#d10000');
    if (!defined('SYSTEM_URL')) define('SYSTEM_URL', 'CHANGE_ME');
    if (!defined('SYSTEM_LOGO')) define('SYSTEM_LOGO', '/assets/img/defaultLogo.webp');
    if (!defined('META_IMAGE_URL')) define('META_IMAGE_URL', '');

    // SERVER DATEN
    if (!defined('SERVER_NAME')) define('SERVER_NAME', 'CHANGE_ME');
    if (!defined('SERVER_CITY')) define('SERVER_CITY', 'Musterstadt');

    // RP DATEN
    if (!defined('RP_ORGTYPE')) define('RP_ORGTYPE', 'Berufsfeuerwehr');
    if (!defined('RP_STREET')) define('RP_STREET', 'Musterweg 0815');
    if (!defined('RP_ZIP')) define('RP_ZIP', '1337');

    // FUNKTIONEN
    if (!defined('CHAR_ID')) define('CHAR_ID', true);
    if (!defined('ENOTF_PREREG')) define('ENOTF_PREREG', true);
    if (!defined('ENOTF_USE_PIN')) define('ENOTF_USE_PIN', true);
    if (!defined('ENOTF_PIN')) define('ENOTF_PIN', '1234');
    if (!defined('ENOTF_REQUIRE_USER_AUTH')) define('ENOTF_REQUIRE_USER_AUTH', false);
    if (!defined('FIRE_INCIDENT_REQUIRE_USER_AUTH')) define('FIRE_INCIDENT_REQUIRE_USER_AUTH', false);
    if (!defined('REGISTRATION_MODE')) define('REGISTRATION_MODE', 'open');
    if (!defined('BASE_PATH')) define('BASE_PATH', '/');
    if (!defined('KB_PUBLIC_ACCESS')) define('KB_PUBLIC_ACCESS', false);

    // RECHTLICHES
    if (!defined('LEGAL_IMPRESSUM_URL')) define('LEGAL_IMPRESSUM_URL', '');
    if (!defined('LEGAL_DATENSCHUTZ_URL')) define('LEGAL_DATENSCHUTZ_URL', '');
}

// Ensure KB_PUBLIC_ACCESS has a default even after successful config load
if (!defined('KB_PUBLIC_ACCESS')) define('KB_PUBLIC_ACCESS', false);
if (!defined('ENOTF_CHAR_LOCK')) define('ENOTF_CHAR_LOCK', false);
if (!defined('ENOTF_JOB_FILTER')) define('ENOTF_JOB_FILTER', false);
