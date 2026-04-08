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

if (!isset($_ENV['DB_HOST'])) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, null, false);
        $dotenv->load();
    } catch (\Throwable $e) {
        // Erlaubt Phinx-CLI-Aufrufe ohne .env (z.B. für `phinx init`).
    }
}

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
            'host'    => $_ENV['DB_HOST'] ?? 'localhost',
            'name'    => $_ENV['DB_NAME'] ?? '',
            'user'    => $_ENV['DB_USER'] ?? '',
            'pass'    => $_ENV['DB_PASS'] ?? '',
            'port'    => (int) ($_ENV['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'testing' => [
            'adapter' => 'mysql',
            'host'    => $_ENV['DB_TEST_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost',
            'name'    => $_ENV['DB_TEST_NAME'] ?? 'intrarp_test',
            'user'    => $_ENV['DB_TEST_USER'] ?? $_ENV['DB_USER'] ?? '',
            'pass'    => $_ENV['DB_TEST_PASS'] ?? $_ENV['DB_PASS'] ?? '',
            'port'    => (int) ($_ENV['DB_TEST_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'version_order' => 'creation',
];
