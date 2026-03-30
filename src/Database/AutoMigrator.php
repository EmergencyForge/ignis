<?php

namespace App\Database;

use PDO;

/**
 * Lightweight auto-migration check.
 * Runs pending migrations automatically on app start.
 * Uses a file-count comparison to avoid scanning all files on every request.
 */
class AutoMigrator
{
    private PDO $pdo;
    private string $migrationsPath;
    private string $cacheFile;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = __DIR__ . '/../../assets/database';
        $this->cacheFile = __DIR__ . '/../../storage/logs/.migration_count';
    }

    /**
     * Check if migrations need to run and execute them if so.
     * This is called on every request but is very cheap (one file_exists + one int comparison).
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

        // Something changed — run the full migration script
        $this->runMigrations();

        // Update cache
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($this->cacheFile, (string)$fileCount);
    }

    private function runMigrations(): void
    {
        try {
            // Ensure migrations table exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS intra_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Get already-run migrations
            $stmt = $this->pdo->query("SELECT migration FROM intra_migrations");
            $executed = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'migration');

            // Scan migration files
            $files = glob($this->migrationsPath . '/*.php');
            if (!$files) return;
            sort($files);

            $ran = 0;
            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $executed)) continue;

                // Read and execute the migration
                $sql = file_get_contents($file);
                if (!$sql) continue;

                // Extract SQL from PHP file (migration files contain raw SQL wrapped in PHP)
                // They use $pdo->exec() or similar patterns
                // We need to include them with $pdo available
                try {
                    $pdo = $this->pdo;
                    include $file;

                    // Mark as executed
                    $markStmt = $this->pdo->prepare("INSERT IGNORE INTO intra_migrations (migration) VALUES (?)");
                    $markStmt->execute([$name]);
                    $ran++;
                } catch (\Exception $e) {
                    \App\Logging\Logger::error("Auto-migration failed for {$name}: " . $e->getMessage());
                    // Don't mark as run, will retry next time
                }
            }

            if ($ran > 0) {
                \App\Logging\Logger::info("Auto-migration: {$ran} migration(s) executed");
            }
        } catch (\Exception $e) {
            \App\Logging\Logger::error("Auto-migration error: " . $e->getMessage());
        }
    }
}
