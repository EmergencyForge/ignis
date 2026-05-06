<?php

/**
 * PHPUnit Bootstrap
 *
 * Loads autoloader, baut den Service-Container, setzt Test-Environment.
 *
 * Optionaler .env.test-Pfad: Wenn vorhanden, werden TEST_DB_*-Variablen
 * auf DB_* gemappt, sodass Integration-Tests gegen die Test-DB laufen können.
 * Unit-Tests funktionieren auch ohne .env.test, weil PHP-DI lazy ist und
 * PDO erst bei Auflösung verbindet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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

    // TEST_DB_* → DB_* mappen, damit PDO-Factory im Container die nutzen kann
    foreach (['HOST', 'PORT', 'USER', 'PASS', 'NAME'] as $key) {
        $src = "TEST_DB_$key";
        $dst = "DB_$key";
        if (isset($_ENV[$src]) && $_ENV[$src] !== '') {
            $_ENV[$dst] = $_ENV[$src];
            putenv("$dst={$_ENV[$src]}");
        }
    }
}

// Service-Container bauen — gleiche Logik wie assets/config/config.php
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
        // Tolerant — Unit-Tests sollen auch ohne DB laufen
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
// die Klasse hier einmal eager — alle nachfolgenden Test-Setups können dann
// $_SESSION['permissions'] frei manipulieren.
class_exists(\App\Auth\Permissions::class);
