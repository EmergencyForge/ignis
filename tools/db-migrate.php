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

// Credentials kommen aus der .env ODER aus echten Umgebungsvariablen —
// Docker/CI-Setups setzen DB_HOST & Co. direkt in die Prozessumgebung
// und haben gar keine (oder eine unvollständige) .env.
if (is_file($root . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable($root);
        $dotenv->load();
    } catch (\Throwable $e) {
        fwrite(STDERR, "[FAIL] .env nicht geladen: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$env = static function (string $key): ?string {
    if (isset($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    $value = getenv($key);
    return $value === false ? null : $value;
};

$db = [];
$missing = [];
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $required) {
    $value = $env($required);
    if ($value === null) {
        $missing[] = $required;
    } else {
        $db[$required] = $value;
    }
}

if ($missing !== []) {
    // Composer post-install/-update läuft auch in CI-Runnern und auf frischen
    // Clones vor dem Setup — dort darf das kein FAILURE sein, sonst bricht
    // jeder `composer install` ab. Migration ist ein reiner Produktiv-Schritt.
    if (count($missing) === 4) {
        fwrite(STDOUT, "[SKIP] Keine DB-Credentials (.env oder Umgebungsvariablen) — Migration übersprungen (CI/Erstinstallation).\n");
        exit(0);
    }
    fwrite(STDERR, "[FAIL] Unvollständige DB-Credentials, es fehlt: " . implode(', ', $missing) . "\n");
    exit(1);
}

try {
    $dsn = "mysql:host={$db['DB_HOST']};dbname={$db['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db['DB_USER'], $db['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    fwrite(STDERR, "[FAIL] DB-Verbindung fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}

echo "ıgnıs Migration\n";
echo "  Host:     {$db['DB_HOST']}\n";
echo "  Database: {$db['DB_NAME']}\n";
echo "  Driver:   Phinx (mit Legacy-Bridge)\n\n";

$migrator = new App\Database\AutoMigrator($pdo);
$migrator->runIfNeeded();

echo "[OK] Migration abgeschlossen.\n";
