<?php

/**
 * Phinx Configuration
 *
 * Liest DB-Credentials aus .env (vlucas/phpdotenv).
 * Migrations liegen unter database/migrations/.
 *
 * Aufruf:
 *   vendor/bin/phinx migrate -e production
 *   vendor/bin/phinx status -e production
 *
 * Im Code wird Phinx programmatisch via App\Database\AutoMigrator aufgerufen,
 * sodass auch Webspaces ohne Shell-Zugang funktionieren.
 */

require_once __DIR__ . '/vendor/autoload.php';

// .env best-effort laden (Produktion + lokale Entwicklung). In der CI gibt es
// keine .env — dort kommen die DB_*-Variablen direkt aus der Prozess-Umgebung.
if (is_file(__DIR__ . '/.env')) {
    try {
        Dotenv\Dotenv::createImmutable(__DIR__, null, false)->load();
    } catch (\Throwable $e) {
        // Erlaubt Phinx-CLI-Aufrufe ohne lesbare .env (z.B. für `phinx init`).
    }
}

// $_ENV ist je nach variables_order leer, selbst wenn OS-Env-Variablen gesetzt
// sind (Standard bei PHP-CLI in CI). Daher robust über $_ENV/$_SERVER/getenv()
// auflösen, sonst landet Phinx auf 'localhost' + leerer DB → [2002] Socket-Fehler.
$env = static function (string $key, ?string $default = null): ?string {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($val === false || $val === null || $val === '') ? $default : (string) $val;
};

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds'      => __DIR__ . '/database/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'production',

        'production' => [
            'adapter' => 'mysql',
            'host'    => $env('DB_HOST', 'localhost'),
            'name'    => $env('DB_NAME', ''),
            'user'    => $env('DB_USER', ''),
            'pass'    => $env('DB_PASS', ''),
            'port'    => (int) $env('DB_PORT', '3306'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'testing' => [
            'adapter' => 'mysql',
            'host'    => $env('DB_TEST_HOST', $env('DB_HOST', 'localhost')),
            'name'    => $env('DB_TEST_NAME', 'intrarp_test'),
            'user'    => $env('DB_TEST_USER', $env('DB_USER', '')),
            'pass'    => $env('DB_TEST_PASS', $env('DB_PASS', '')),
            'port'    => (int) $env('DB_TEST_PORT', '3306'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'version_order' => 'creation',
];
