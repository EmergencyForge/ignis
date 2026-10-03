<?php

/**
 * PHPUnit Bootstrap
 *
 * Loads autoloader, baut den Service-Container, setzt Test-Environment.
 *
 * TEST_DB_*-Variablen (aus .env.test oder der Umgebung) werden auf DB_*
 * gemappt, sodass Integration-Tests gegen die Test-DB laufen können.
 * Unit-Tests funktionieren auch ohne .env.test, weil PHP-DI lazy ist und
 * PDO erst bei Auflösung verbindet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Plugin-Klassen für Tests autoloaden, unabhängig vom Aktiv-Status in
// der Datenbank: getestet wird der Code, nicht die Freischaltung.
foreach (glob(__DIR__ . '/../plugins/*/manifest.php') ?: [] as $pluginManifestFile) {
    $pluginManifest = require $pluginManifestFile;
    if (!is_array($pluginManifest)) {
        continue;
    }
    foreach ((array) ($pluginManifest['autoload'] ?? []) as $pluginNsPrefix => $pluginSrcDir) {
        $pluginBaseDir = dirname($pluginManifestFile) . '/' . trim((string) $pluginSrcDir, '/\\');
        spl_autoload_register(static function (string $class) use ($pluginNsPrefix, $pluginBaseDir): void {
            if (!str_starts_with($class, (string) $pluginNsPrefix)) {
                return;
            }
            $file = $pluginBaseDir . '/' . str_replace('\\', '/', substr($class, strlen((string) $pluginNsPrefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
unset($pluginManifestFile, $pluginManifest, $pluginNsPrefix, $pluginSrcDir, $pluginBaseDir);

// PHP-DI 7.0 hat ein paar PHP-8.4-Deprecations, die in Tests nur Noise sind
error_reporting(E_ALL & ~E_DEPRECATED);

// Test-Environment-Defaults
$_ENV['APP_ENV']   = 'testing';
$_ENV['LOG_LEVEL'] = 'error';
$_ENV['LOG_PATH']  = __DIR__ . '/../storage/logs';

// Optional: .env.test laden (für Integration-Tests)
$envTest = __DIR__ . '/../.env.test';
if (is_file($envTest)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envTest), '.env.test');
    $dotenv->load();
}

// TEST_DB_* → DB_* mappen, damit PDO-Factory im Container die nutzen kann.
// Die CI setzt TEST_DB_* in der Prozess-Umgebung, die ohne E in
// variables_order nicht in $_ENV landet; env_value() liest auch getenv().
// DB_* selbst bleibt außen vor: im App-Container zeigt es auf die App-DB.
foreach (['HOST', 'PORT', 'USER', 'PASS', 'NAME'] as $key) {
    $value = env_value("TEST_DB_$key");
    if ($value !== null && $value !== '') {
        $_ENV["DB_$key"] = $value;
        putenv("DB_$key=$value");
    }
}

// Service-Container bauen (gleiche Logik wie assets/config/config.php)
$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->useAutowiring(true);
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$GLOBALS['app_container'] = $containerBuilder->build();

// Eloquent eager booten, falls Test-DB-Credentials vorhanden sind. Bei Unit-Tests
// ohne DB-Credentials skippen wir das, damit der Bootstrap nicht crasht.
if (!empty($_ENV['DB_HOST']) && !empty($_ENV['DB_NAME'])) {
    try {
        $GLOBALS['app_container']->get(\Illuminate\Database\Capsule\Manager::class);
    } catch (\Throwable $e) {
        // Tolerant: Unit-Tests sollen auch ohne DB laufen
    }
}

// Suppress session warnings in tests
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// App\Auth\Permissions hat einen Side-Effect am File-Body, der beim ersten
// Class-Autoload $_SESSION['permissions'] aus der DB lädt (basierend auf
// $_SESSION['userid']). In Tests ohne userid würde der Effect $_SESSION mit
// einem leeren Array clobbern und Permission-Tests kaputt machen. Wir laden
// die Klasse hier einmal eager, alle nachfolgenden Test-Setups können dann
// $_SESSION['permissions'] frei manipulieren.
class_exists(\App\Auth\Permissions::class);
