<?php

namespace App\Database;

use PDO;

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

        // Cache file with fallback
        $logDir = $this->appRoot . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $this->cacheFile = is_writable($logDir)
            ? $logDir . '/.migration_count'
            : sys_get_temp_dir() . '/intrarp_migration_count';
    }

    public function runIfNeeded(): void
    {
        $files = glob($this->migrationsPath . '/*.php');
        if (!$files) return;

        $fileCount = count($files);

        // Fast path: nothing changed
        if (file_exists($this->cacheFile) && (int)@file_get_contents($this->cacheFile) === $fileCount) {
            return;
        }

        // Fresh install? Show waiting page, run migrations, redirect.
        if ($this->isFreshInstall() && php_sapi_name() !== 'cli') {
            $this->freshInstall();
            return;
        }

        // Existing install with new migrations: run silently
        $this->executeMigrations();
        @file_put_contents($this->cacheFile, (string)$fileCount);
    }

    private function isFreshInstall(): bool
    {
        try {
            return $this->pdo->query("SHOW TABLES LIKE 'intra_users'")->rowCount() === 0;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function freshInstall(): void
    {
        // Send the waiting page to the browser
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo <<<'HTML'
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>intraRP — Initialisierung</title>
<style>
body{background:#1a1820;color:#bbbac1;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center;max-width:420px}
.spinner{width:40px;height:40px;border:3px solid #3d3a44;border-top-color:#d10000;border-radius:50%;animation:s .8s linear infinite;margin:0 auto 20px}
@keyframes s{to{transform:rotate(360deg)}}
h2{color:#fff;font-size:1.2rem;margin-bottom:8px}
p{font-size:.85rem;opacity:.6}
</style></head><body><div class="box">
<div class="spinner"></div>
<h2>Datenbank wird initialisiert...</h2>
<p>Die Tabellen werden erstellt. Dies dauert nur wenige Sekunden.</p>
</div></body></html>
HTML;

        // Flush to browser so user sees the page immediately
        if (connection_status() === CONNECTION_NORMAL) {
            flush();
            if (function_exists('fastcgi_finish_request')) {
                // FPM: finish the response, continue PHP execution in background
                fastcgi_finish_request();
            }
        }

        // Now run migrations (browser already shows the waiting page)
        $this->executeMigrations();

        $files = glob($this->migrationsPath . '/*.php');
        @file_put_contents($this->cacheFile, (string)count($files ?: []));

        // If fastcgi_finish_request wasn't available, redirect via JS
        // (the HTML above already rendered, so we can't send headers)
        if (!function_exists('fastcgi_finish_request')) {
            echo '<script>setTimeout(function(){location.reload()},500)</script>';
        }

        exit;
    }

    private function executeMigrations(): void
    {
        if (!file_exists($this->initScript)) return;

        try {
            $pdo = $this->pdo;
            $projectRoot = $this->appRoot;
            $__autoMigrator = true;

            $prevLevel = ob_get_level();
            ob_start();
            require $this->initScript;
        } catch (\Exception $e) {
            \App\Logging\Logger::error("Auto-migration failed: " . $e->getMessage());
        } finally {
            // Clean exactly the buffers we and the script added
            while (ob_get_level() > $prevLevel) {
                ob_end_clean();
            }
        }
    }
}
