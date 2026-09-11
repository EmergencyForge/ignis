<?php

// Autoloader muss zuerst geladen werden
require_once __DIR__ . '/../../vendor/autoload.php';

// .env laden, BEVOR der Container gebaut wird — die Capsule-Konfiguration in
// config/container.php liest die DB-Werte beim Eager-Boot. createImmutable
// überschreibt keine bereits gesetzten Werte, ein vorheriger Bootstrap-Schritt
// oder der Webserver behalten also Vorrang.
//
// safeLoad statt load: eine Instanz, die ihre Werte per SetEnv oder aus einem
// FPM-Pool bekommt, hat gar keine .env — load() würfe dort eine
// InvalidPathException, obwohl längst alles Nötige gesetzt ist.
//
// Das Tor prüft bewusst nur $_ENV und nicht env_value(): eine vorhandene .env
// soll die Container-Umgebung schlagen dürfen, damit die lokal laufende
// Web-App gegen die entfernte Dev-DB zeigen kann, während daneben die
// CLI-Migrationen auf der Compose-DB arbeiten. Mit env_value() gewänne hier
// die Container-Variable und die .env bliebe ungelesen.
if (empty($_ENV['DB_HOST'])) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../', null, false)->safeLoad();
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
        SessionManager::setPermissions(
            Permissions::retrieveFromDatabase((int) SessionManager::userId())
        );
    }
}

// Legacy-PDO-Verbindung aufbauen und in den Container schieben. Der App-Code
// läuft komplett über Eloquent — diese Verbindung existiert nur noch für die
// Migrations-Infrastruktur (AutoMigrator, TwigToVisualMigrator) und für
// Konsumenten, die PDO::class aus dem Container ziehen (Console-Commands,
// Plugin-API-Controller). Idempotent: kann mehrfach pro Request laufen.
require_once __DIR__ . '/database.php';
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
    $configManager = new ConfigManager();
    $configManager->loadAndDefineConfig();
} catch (Exception $e) {
    // Fallback to default values if database is not available or table doesn't exist
    \App\Logging\Logger::warning("Could not load config from database: " . $e->getMessage());

    // BASIS DATEN - Fallback defaults
    if (!defined('API_KEY')) define('API_KEY', 'CHANGE_ME');
    if (!defined('SYSTEM_NAME')) define('SYSTEM_NAME', 'ıgnıs');
    if (!defined('SYSTEM_COLOR')) define('SYSTEM_COLOR', \App\Helpers\Theme::DEFAULT_ACCENT);
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
