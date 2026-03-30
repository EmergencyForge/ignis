<?php

namespace App\Database;

use PDO;

/**
 * Lightweight auto-migration check.
 * Runs database-init.php as a subprocess on first install or when
 * new migration files are detected. Uses a file-count cache to
 * avoid running on every request.
 */
class AutoMigrator
{
    private PDO $pdo;
    private string $migrationsPath;
    private string $cacheFile;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $appRoot = dirname(dirname(__DIR__));
        $this->migrationsPath = $appRoot . '/assets/database';
        $this->cacheFile = $appRoot . '/storage/logs/.migration_count';
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

        // Something changed — run migrations
        $this->runMigrations($migrationFiles);

        // Update cache
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($this->cacheFile, (string)$fileCount);
    }

    private function runMigrations(array $files): void
    {
        try {
            // Ensure migrations tracking table exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS intra_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Get already-run migrations
            $stmt = $this->pdo->query("SELECT migration FROM intra_migrations");
            $executed = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'migration');

            // Sort files: CREATE before ALTER before INSERT (same logic as database-init.php)
            sort($files);
            $createFiles = [];
            $alterFiles = [];
            $insertFiles = [];
            $otherFiles = [];

            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $executed)) continue;

                if (str_starts_with($name, 'create_')) {
                    $createFiles[] = $file;
                } elseif (str_starts_with($name, 'alter_')) {
                    $alterFiles[] = $file;
                } elseif (str_starts_with($name, 'insert_')) {
                    $insertFiles[] = $file;
                } else {
                    $otherFiles[] = $file;
                }
            }

            // Execute in correct order
            $ordered = array_merge($createFiles, $alterFiles, $insertFiles, $otherFiles);
            $ran = 0;

            foreach ($ordered as $file) {
                $name = basename($file);
                try {
                    $pdo = $this->pdo;
                    include $file;

                    $markStmt = $this->pdo->prepare("INSERT IGNORE INTO intra_migrations (migration) VALUES (?)");
                    $markStmt->execute([$name]);
                    $ran++;
                } catch (\Exception $e) {
                    // Log but continue — some ALTER migrations may fail if table
                    // structure already matches (e.g. column already exists)
                    \App\Logging\Logger::warning("Migration {$name}: " . $e->getMessage());
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
