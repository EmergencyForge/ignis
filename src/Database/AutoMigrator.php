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

        $isFreshInstall = $this->isFreshInstall();

        // Run migrations (all output captured silently)
        $this->runMigrations();

        // Update cache
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($this->cacheFile, (string)$fileCount);

        // On fresh install via web: show success page and redirect
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
        if (!file_exists($this->initScript)) {
            \App\Logging\Logger::warning("Auto-migration: database-init.php not found");
            return;
        }

        try {
            // Capture ALL output — database-init.php and migration files echo progress
            ob_start();
            $pdo = $this->pdo;
            require $this->initScript;
            $output = ob_get_clean();

            if (!empty($output)) {
                \App\Logging\Logger::info("Auto-migration completed (" . strlen($output) . " bytes output)");
            }
        } catch (\Exception $e) {
            // Clean up any nested output buffers
            while (ob_get_level() > 0) ob_end_clean();
            \App\Logging\Logger::error("Auto-migration failed: " . $e->getMessage());
        }
    }

    private function showCompletePage(): void
    {
        // Clean any leftover output buffers
        while (ob_get_level() > 0) ob_end_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $redirect = $_SERVER['REQUEST_URI'] ?? '/';

        echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="refresh" content="2;url={$redirect}">
    <title>intraRP — Initialisierung abgeschlossen</title>
    <style>
        body { background: #1a1820; color: #bbbac1; font-family: system-ui, sans-serif;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .init-box { text-align: center; max-width: 420px; }
        .check { font-size: 3rem; color: #28a745; margin-bottom: 16px; }
        h2 { color: #fff; font-size: 1.2rem; margin-bottom: 8px; }
        p { font-size: 0.85rem; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="init-box">
        <div class="check">&#10003;</div>
        <h2>Datenbank erfolgreich initialisiert</h2>
        <p>Du wirst in wenigen Sekunden weitergeleitet...</p>
    </div>
</body>
</html>
HTML;
    }
}
