<?php

namespace App\Database;

use PDO;

/**
 * Lightweight auto-migration check.
 * Delegates to setup/database-init.php which has the correct migration
 * order hardcoded. Uses a file-count cache to avoid running on every request.
 */
class AutoMigrator
{
    private PDO $pdo;
    private string $migrationsPath;
    private string $cacheFile;
    private string $initScript;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $appRoot = dirname(dirname(__DIR__));
        $this->migrationsPath = $appRoot . '/assets/database';
        $this->cacheFile = $appRoot . '/storage/logs/.migration_count';
        $this->initScript = $appRoot . '/setup/database-init.php';
    }

    /**
     * Check if migrations need to run and execute them if so.
     * Very cheap on every request: one file count + one int comparison.
     */
    public function runIfNeeded(): void
    {
        $migrationFiles = glob($this->migrationsPath . '/*.php');
        if ($migrationFiles === false) return;

        $fileCount = count($migrationFiles);

        // Fast path: if file count matches cached count, nothing to do
        if (file_exists($this->cacheFile)) {
            $cached = (int)file_get_contents($this->cacheFile);
            if ($cached === $fileCount) return;
        }

        // Check if this is a fresh install (no tables yet)
        $isFreshInstall = $this->isFreshInstall();

        // Show waiting page for web requests during fresh install
        if ($isFreshInstall && php_sapi_name() !== 'cli') {
            $this->showInitPage();
        }

        // Run database-init.php
        $this->runMigrations();

        // Update cache
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($this->cacheFile, (string)$fileCount);

        // Reload page after fresh install
        if ($isFreshInstall && php_sapi_name() !== 'cli') {
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }

    private function isFreshInstall(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'intra_users'");
            return $stmt->rowCount() === 0;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function showInitPage(): void
    {
        // Flush a loading page to the browser before running migrations
        if (headers_sent()) return;

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>intraRP — Initialisierung</title>';
        echo '<style>
            body { background: #1a1820; color: #bbbac1; font-family: system-ui, sans-serif;
                   display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .init-box { text-align: center; max-width: 400px; }
            .spinner { width: 40px; height: 40px; border: 3px solid #3d3a44; border-top-color: #d10000;
                       border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
            @keyframes spin { to { transform: rotate(360deg); } }
            h2 { color: #fff; font-size: 1.2rem; margin-bottom: 8px; }
            p { font-size: 0.85rem; opacity: 0.6; }
        </style></head><body><div class="init-box">';
        echo '<div class="spinner"></div>';
        echo '<h2>Datenbank wird initialisiert...</h2>';
        echo '<p>Die Tabellen werden erstellt. Dies dauert nur wenige Sekunden.</p>';
        echo '</div></body></html>';

        // Flush to browser
        if (function_exists('ob_flush')) { @ob_flush(); }
        flush();
    }

    private function runMigrations(): void
    {
        if (!file_exists($this->initScript)) {
            \App\Logging\Logger::warning("Auto-migration: database-init.php not found");
            return;
        }

        try {
            ob_start();
            $pdo = $this->pdo; // Make available to database-init.php
            require $this->initScript;
            $output = ob_get_clean();

            if (!empty($output)) {
                \App\Logging\Logger::info("Auto-migration output: " . substr($output, 0, 2000));
            }
        } catch (\Exception $e) {
            if (ob_get_level() > 0) ob_end_clean();
            \App\Logging\Logger::error("Auto-migration failed: " . $e->getMessage());
        }
    }
}
