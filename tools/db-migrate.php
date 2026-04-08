<?php

/**
 * intraRP — Production DB Migration CLI
 *
 * Wird von Composer-Hooks (post-install-cmd, post-update-cmd) und manuell
 * aufgerufen. Macht zwei Dinge:
 *   1. Bridge: existierende intra_migrations-Tabelle (Pre-Phinx) wird in
 *      phinxlog gespiegelt, damit Phinx historische Migrations als „erledigt"
 *      sieht.
 *   2. Phinx-Migration: nur ausstehende Migrations werden tatsächlich gefahren.
 *
 * Webspace-tauglich: läuft via @php in composer.json — kein Shell-Zugang nötig.
 *
 * Aufruf:
 *   composer db:migrate
 *   php tools/db-migrate.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

try {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->load();
} catch (\Throwable $e) {
    fwrite(STDERR, "[FAIL] .env nicht geladen: " . $e->getMessage() . "\n");
    exit(1);
}

foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $required) {
    if (!isset($_ENV[$required])) {
        fwrite(STDERR, "[FAIL] Umgebungsvariable $required fehlt in .env\n");
        exit(1);
    }
}

try {
    $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    fwrite(STDERR, "[FAIL] DB-Verbindung fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}

echo "intraRP Migration\n";
echo "  Host:     {$_ENV['DB_HOST']}\n";
echo "  Database: {$_ENV['DB_NAME']}\n";
echo "  Driver:   Phinx (mit Legacy-Bridge)\n\n";

$migrator = new App\Database\AutoMigrator($pdo);
$migrator->runIfNeeded();

echo "[OK] Migration abgeschlossen.\n";
