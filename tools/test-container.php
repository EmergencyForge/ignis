<?php

/**
 * Smoke-Test: PHP-DI Container kann alle registrierten Services auflösen.
 *
 * Simuliert den Web-Bootstrap (autoload + dotenv + container) und ruft
 * dann jeden konfigurierten Service einmal ab.
 *
 * Aufruf: php tools/test-container.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// .env laden, sonst greift der PDO-Fallback nicht
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "intraRP Container Smoke-Test\n\n";

// 1. Container bauen — gleiche Logik wie assets/config/config.php
$builder = new \DI\ContainerBuilder();
$builder->useAutowiring(true);
$builder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $builder->build();
$GLOBALS['app_container'] = $container;

echo "[1] Container gebaut.\n";

// 2. Jeden registrierten Service auflösen
$services = [
    PDO::class,
    \Psr\Log\LoggerInterface::class,
    \App\Logging\Logger::class,
    \App\Config\ConfigManager::class,
    \App\Session\SessionManager::class,
];

foreach ($services as $svc) {
    try {
        $instance = $container->get($svc);
        $type = get_class($instance);
        echo "[OK] $svc → $type\n";
    } catch (\Throwable $e) {
        echo "[FAIL] $svc: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 3. app()-Helper testen
echo "\n[2] Test app()-Helper:\n";
try {
    $logger = app(\Psr\Log\LoggerInterface::class);
    echo "[OK] app(LoggerInterface::class) → " . get_class($logger) . "\n";
    $containerFromHelper = app();
    echo "[OK] app() → " . get_class($containerFromHelper) . "\n";
} catch (\Throwable $e) {
    echo "[FAIL] app() helper: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Logger funktioniert
echo "\n[3] Logger-Funktionalität:\n";
try {
    app(\Psr\Log\LoggerInterface::class)->info('Container smoke-test ran successfully');
    echo "[OK] Logger akzeptiert Messages.\n";
} catch (\Throwable $e) {
    echo "[FAIL] Logger: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. PDO funktioniert
echo "\n[4] PDO-Funktionalität:\n";
try {
    $pdo = app(PDO::class);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "[OK] PDO verbunden, MySQL-Version: $version\n";
} catch (\Throwable $e) {
    echo "[FAIL] PDO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n[ERFOLG] Container-Smoke-Test bestanden.\n";
