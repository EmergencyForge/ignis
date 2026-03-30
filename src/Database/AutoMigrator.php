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
    private string $appRoot;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->appRoot = dirname(dirname(__DIR__));
        $this->migrationsPath = $this->appRoot . '/assets/database';
        $this->initScript = $this->appRoot . '/setup/database-init.php';

        // Use multiple fallback paths for the cache file
        $logDir = $this->appRoot . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $this->cacheFile = is_writable($logDir)
            ? $logDir . '/.migration_count'
            : sys_get_temp_dir() . '/intrarp_migration_count_' . md5($this->appRoot);
    }

    public function runIfNeeded(): void
    {
        $migrationFiles = glob($this->migrationsPath . '/*.php');
        if ($migrationFiles === false) return;

        $fileCount = count($migrationFiles);

        // Fast path: cache matches → nothing to do
        if (file_exists($this->cacheFile)) {
            $cached = (int)@file_get_contents($this->cacheFile);
            if ($cached === $fileCount) return;
        }

        $isFreshInstall = $this->isFreshInstall();

        // Run migrations silently
        $this->runMigrations();

        // Write cache
        @file_put_contents($this->cacheFile, (string)$fileCount);

        // Fresh install via web: show success + redirect
        if ($isFreshInstall && php_sapi_name() !== 'cli' && !headers_sent()) {
            $this->showCompletePage();
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

    private function runMigrations(): void
    {
        if (!file_exists($this->initScript)) return;

        // Suppress ALL output from database-init.php and its migrations.
        // Some migration files use echo/print directly, so we need to capture
        // everything and discard it.
        $prevLevel = ob_get_level();
        ob_start();

        // Register shutdown function to clean buffers even if exit() is called
        $prevLevelRef = $prevLevel;
        register_shutdown_function(function () use ($prevLevelRef) {
            while (ob_get_level() > $prevLevelRef) {
                ob_end_clean();
            }
        });

        try {
            $pdo = $this->pdo;
            $projectRoot = $this->appRoot;
            require $this->initScript;
        } catch (\Exception $e) {
            \App\Logging\Logger::error("Auto-migration failed: " . $e->getMessage());
        }

        // Clean ALL output buffers added during migration
        while (ob_get_level() > $prevLevel) {
            ob_end_clean();
        }
    }

    private function showCompletePage(): void
    {
        while (ob_get_level() > 0) ob_end_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        $redirect = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');

        echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="refresh" content="2;url={$redirect}">
    <title>intraRP — Initialisierung abgeschlossen</title>
    <style>
        body{background:#1a1820;color:#bbbac1;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .box{text-align:center;max-width:420px}
        .ok{font-size:3rem;color:#28a745;margin-bottom:16px}
        h2{color:#fff;font-size:1.2rem;margin-bottom:8px}
        p{font-size:.85rem;opacity:.6}
    </style>
</head>
<body><div class="box">
    <div class="ok">&#10003;</div>
    <h2>Datenbank erfolgreich initialisiert</h2>
    <p>Du wirst in wenigen Sekunden weitergeleitet...</p>
</div></body></html>
HTML;
    }
}
