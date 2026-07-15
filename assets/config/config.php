<?php

// Autoloader muss zuerst geladen werden
require_once __DIR__ . '/../../vendor/autoload.php';

// .env laden, BEVOR der Container gebaut wird — Eloquent-Capsule liest die
// DB-Credentials direkt aus $_ENV beim Eager-Boot. createImmutable überschreibt
// keine bereits gesetzten Werte (z.B. wenn ein vorheriger Bootstrap-Schritt
// schon dotenv geladen hat oder Variablen vom Webserver gesetzt sind).
if (empty($_ENV['DB_HOST'])) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../', null, false)->load();
}

use App\Auth\Permissions;
use App\Config\ConfigManager;
use App\Logging\ErrorHandler;
use App\Session\SessionManager;

// ============================================================================
// Globales Error-Handling & Logging registrieren
// ============================================================================
// MUSS vor dem Container-Build passieren, damit Vendor-Deprecations (z.B. von
// PHP-DI selbst) vom isVendorFile()-Filter im ErrorHandler abgefangen werden
// und nicht im Browser landen.
ErrorHandler::register();

// ============================================================================
// Service-Container (PHP-DI) bootstrappen
// ============================================================================
// Wird einmalig pro Request gebaut und in $GLOBALS abgelegt, damit die
// app()-Helper-Funktion aus src/helpers.php darauf zugreifen kann.
// Bestehender Code bleibt unangetastet — der Container ist additiv.
if (!isset($GLOBALS['app_container'])) {
    $containerBuilder = new \DI\ContainerBuilder();
    $containerBuilder->useAutowiring(true);
    $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
    $GLOBALS['app_container'] = $containerBuilder->build();

    // Eloquent eager booten — ohne setAsGlobal() würden Models keine Verbindung
    // finden. Idempotent: Capsule ist im Container ein Singleton.
    $GLOBALS['app_container']->get(\Illuminate\Database\Capsule\Manager::class);
}

// ============================================================================
// Session mit Sicherheitsoptimierungen starten
// ============================================================================
SessionManager::start();

// ============================================================================
// Permissions mit TTL (Time-to-Live)
// ============================================================================
// Permissions werden alle 5 Minuten neu aus der DB geladen.
// Das stellt sicher, dass Änderungen an Rollen zeitnah wirksam werden.
if (SessionManager::isLoggedIn()) {
    $permissionsTTL = 300; // 5 Minuten

    if (!SessionManager::has('permissions') || SessionManager::permissionsAge() > $permissionsTTL) {
        require_once __DIR__ . '/database.php';
        SessionManager::setPermissions(
            Permissions::retrieveFromDatabase($pdo, (int) SessionManager::userId())
        );
    }
}

// Load configuration from database
require_once __DIR__ . '/database.php';

// Existierende $pdo-Instanz in den Container schieben, damit Legacy-Code
// (der direkt $pdo nutzt) und neuer DI-Code (app(PDO::class)) dieselbe
// Verbindung verwenden. Idempotent: kann mehrfach pro Request laufen.
if (isset($pdo) && $pdo instanceof PDO) {
    $GLOBALS['app_container']->set(PDO::class, $pdo);
}

// Auto-run pending database migrations (lightweight file-count check)
try {
    $autoMigrator = new \App\Database\AutoMigrator($pdo);
    $autoMigrator->runIfNeeded();
} catch (Exception $e) {
    // Non-critical: log and continue (first install may not have all tables yet)
    \App\Logging\Logger::warning("Auto-migration check failed: " . $e->getMessage());
}

// Aktive Plugins anbinden: Autoloading für ihre Klassen und Gate-Policies
// registrieren. Muss vor dem Routing laufen, weil Plugin-Controller sonst
// beim Dispatch nicht auflösbar wären. Schlägt fehl-tolerant fehl — ohne
// ladbare Plugins läuft der Kern normal weiter.
try {
    $pluginLoader = $GLOBALS['app_container']->get(\App\Plugins\PluginLoader::class);
    $pluginLoader->registerAutoloading();
    $pluginLoader->registerPolicies();
} catch (\Throwable $e) {
    \App\Logging\Logger::warning('Plugin-Bootstrap fehlgeschlagen: ' . $e->getMessage());
}

try {
    $configManager = new ConfigManager($pdo);
    $configManager->loadAndDefineConfig();
} catch (Exception $e) {
    // Fallback to default values if database is not available or table doesn't exist
    \App\Logging\Logger::warning("Could not load config from database: " . $e->getMessage());

    // BASIS DATEN - Fallback defaults
    if (!defined('API_KEY')) define('API_KEY', 'CHANGE_ME');
    if (!defined('SYSTEM_NAME')) define('SYSTEM_NAME', 'ıgnıs');
    if (!defined('SYSTEM_COLOR')) define('SYSTEM_COLOR', '#FF4D00');
    if (!defined('SYSTEM_URL')) define('SYSTEM_URL', 'CHANGE_ME');
    if (!defined('SYSTEM_LOGO')) define('SYSTEM_LOGO', '/assets/img/ignis-wordmark.svg');
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

    // FEDERATION
    if (!defined('FEDERATION_ENABLED')) define('FEDERATION_ENABLED', false);
    if (!defined('FEDERATION_INSTANCE_ID')) define('FEDERATION_INSTANCE_ID', '');
    if (!defined('FEDERATION_INSTANCE_NAME')) define('FEDERATION_INSTANCE_NAME', '');
}

// Ensure KB_PUBLIC_ACCESS has a default even after successful config load
if (!defined('KB_PUBLIC_ACCESS')) define('KB_PUBLIC_ACCESS', false);
if (!defined('ENOTF_CHAR_LOCK')) define('ENOTF_CHAR_LOCK', false);
if (!defined('ENOTF_JOB_FILTER')) define('ENOTF_JOB_FILTER', false);
