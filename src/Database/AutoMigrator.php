<?php

namespace App\Database;

use PDO;
use PDOException;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * AutoMigrator — programmatischer Phinx-Wrapper.
 *
 * Aufgaben:
 *   1. Auf Webspaces ohne Shell-Zugang Phinx-Migrations einbettbar machen.
 *   2. Bridge: Bestehende Installs (mit intra_migrations-Tabelle) werden nahtlos
 *      auf Phinx umgestellt, ohne dass die 147 historischen Migrations erneut laufen.
 *   3. Fresh-Install-UX (Wartebildschirm + Auto-Reload) erhalten.
 */
class AutoMigrator
{
    private PDO $pdo;
    private string $appRoot;
    private string $cacheFile;
    private string $migrationsPath;

    /** @var list<string> zusätzliche Migrations-Verzeichnisse (Plugins) */
    private array $extraMigrationPaths = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->appRoot        = dirname(__DIR__, 2);
        $this->migrationsPath = $this->appRoot . '/database/migrations';
        // Plugin-Migrations laufen unabhängig vom Aktiv-Status mit — ein
        // deaktiviertes Plugin behält sein Schema, damit beim Reaktivieren
        // nichts fehlt. phinx.php kennt dieselben Pfade.
        $this->extraMigrationPaths = \App\Plugins\PluginLoader::migrationPaths();

        $logDir = $this->appRoot . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        // Eigener Name (.phinx_migration_count statt .migration_count) damit
        // ein Upgrade von der Pre-Phinx-Version nicht versehentlich den alten
        // Cache-Wert (Count von assets/database) wiederverwendet.
        $this->cacheFile = is_writable($logDir)
            ? $logDir . '/.phinx_migration_count'
            : sys_get_temp_dir() . '/intrarp_phinx_migration_count';
    }

    public function runIfNeeded(): void
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        foreach ($this->extraMigrationPaths as $dir) {
            $files = array_merge($files, glob($dir . '/*.php') ?: []);
        }
        if (count($files) === 0) {
            return;
        }
        $fileCount = count($files);

        // Fast path: nichts geändert seit letztem Lauf — aber zusätzlich
        // prüfen, dass tatsächlich keine Migration im phinxlog fehlt.
        // Reiner File-Count-Vergleich übersieht Fälle wo eine Migration
        // hinzugefügt wurde und der vorherige runPhinx() fehlschlug
        // (Phinx-Output ging ins Log, Cache wurde trotzdem aktualisiert).
        if (
            file_exists($this->cacheFile)
            && (int) @file_get_contents($this->cacheFile) === $fileCount
            && !$this->hasPendingMigrations($files)
        ) {
            return;
        }

        // Fresh install: Wartebildschirm anzeigen, dann migrieren, dann reload
        if ($this->isFreshInstall() && php_sapi_name() !== 'cli') {
            $this->freshInstall();
            return;
        }

        // Bridge: bei Erstkontakt mit Phinx vorhandene intra_migrations übernehmen
        $this->bridgeLegacyMigrationsTable();

        // Bestehender Install mit neuen Migrations: still im Hintergrund laufen.
        // Cache nur bei Erfolg aktualisieren — sonst denkt der nächste Request
        // „nichts zu tun" und der Bug wird unsichtbar.
        if ($this->runPhinx()) {
            @file_put_contents($this->cacheFile, (string) $fileCount);
        }
    }

    /**
     * Prüft ob in der phinxlog-Tabelle alle Migrations-Dateien als „up"
     * vermerkt sind. Verlässt sich auf das `{timestamp}_…`-Filenamen-Pattern.
     */
    private function hasPendingMigrations(array $files): bool
    {
        try {
            $hasTable = $this->pdo->query("SHOW TABLES LIKE 'phinxlog'")->rowCount() > 0;
            if (!$hasTable) {
                return true;
            }

            $rows = $this->pdo->query("SELECT version FROM phinxlog")->fetchAll(\PDO::FETCH_COLUMN);
            $applied = array_flip(array_map('strval', (array) $rows));

            foreach ($files as $path) {
                $base = basename($path, '.php');
                if (preg_match('/^(\d{14})_/', $base, $m) && !isset($applied[$m[1]])) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            // Im Zweifel runPhinx triggern, statt zu skippen.
            return true;
        }
    }

    private function isFreshInstall(): bool
    {
        try {
            return $this->pdo->query("SHOW TABLES LIKE 'intra_users'")->rowCount() === 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function freshInstall(): void
    {
        // Erst migrieren (still), dann erfolgsseite mit auto-redirect
        $this->bridgeLegacyMigrationsTable();
        $this->runPhinx();

        $files = glob($this->migrationsPath . '/*.php') ?: [];
        @file_put_contents($this->cacheFile, (string) count($files));

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        $url = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');
        echo <<<HTML
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="1;url={$url}">
<title>ıgnıs — Initialisierung</title>
<style>
body{background:#1a1820;color:#bbbac1;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center;max-width:420px}
.ok{font-size:3rem;color:#28a745;margin-bottom:16px}
h2{color:#fff;font-size:1.2rem;margin-bottom:8px}
p{font-size:.85rem;opacity:.6}
</style></head><body><div class="box">
<div class="ok">&#10003;</div>
<h2>Datenbank erfolgreich initialisiert</h2>
<p>Du wirst automatisch weitergeleitet...</p>
</div></body></html>
HTML;
        exit;
    }

    /**
     * Bridge: Wenn ein bestehender Install eine intra_migrations-Tabelle hat
     * (Pre-Phinx), übertragen wir alle bekannten Einträge in phinxlog. So
     * verhindern wir, dass Phinx die 147 historischen Migrations erneut laufen
     * lässt.
     *
     * Idempotent: Wird nichts gemacht, falls intra_migrations fehlt oder phinxlog
     * bereits gefüllt ist.
     */
    private function bridgeLegacyMigrationsTable(): void
    {
        try {
            $hasLegacy = $this->pdo->query("SHOW TABLES LIKE 'intra_migrations'")->rowCount() > 0;
            if (!$hasLegacy) {
                return;
            }

            // Phinxlog anlegen, falls nicht vorhanden
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `phinxlog` (
                    `version` BIGINT(20) NOT NULL,
                    `migration_name` VARCHAR(100) DEFAULT NULL,
                    `start_time` TIMESTAMP NULL DEFAULT NULL,
                    `end_time` TIMESTAMP NULL DEFAULT NULL,
                    `breakpoint` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Wenn phinxlog schon Einträge hat, ist der Bridge bereits gelaufen
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM phinxlog")->fetchColumn();
            if ($count > 0) {
                return;
            }

            // Mapping: Legacy-Filename → Phinx-Version (Timestamp aus Migration-Filename)
            $mapping = $this->buildLegacyToPhinxMapping();
            if (empty($mapping)) {
                return;
            }

            // Alle Einträge aus intra_migrations holen, in phinxlog spiegeln
            $stmt = $this->pdo->query("SELECT migration FROM intra_migrations");
            $insert = $this->pdo->prepare("
                INSERT IGNORE INTO phinxlog (version, migration_name, start_time, end_time, breakpoint)
                VALUES (?, ?, NOW(), NOW(), 0)
            ");

            $bridged = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $legacy = $row['migration'];
                if (!isset($mapping[$legacy])) {
                    continue;
                }
                [$version, $className] = $mapping[$legacy];
                $insert->execute([$version, $className]);
                $bridged++;
            }

            \App\Logging\Logger::info("AutoMigrator: $bridged Legacy-Migrations zu phinxlog gebridged.");
        } catch (\Throwable $e) {
            \App\Logging\Logger::error("AutoMigrator bridge failed: " . $e->getMessage());
        }
    }

    /**
     * Scannt database/migrations/ und baut ein Mapping:
     *   ['legacy_filename.php' => [version, className], ...]
     *
     * Phinx-Migration-Filenames haben das Format: {timestamp}_{legacy_stem}.php
     * Klassennamen sind die CamelCase-Variante des legacy_stem.
     */
    private function buildLegacyToPhinxMapping(): array
    {
        // Plugin-Migrations gehören mit ins Mapping — sonst würde die Bridge
        // bei Alt-Installationen deren Legacy-Einträge nicht spiegeln und
        // Phinx liefe bereits angewendete Migrationen erneut.
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        foreach ($this->extraMigrationPaths as $dir) {
            $files = array_merge($files, glob($dir . '/*.php') ?: []);
        }
        $mapping = [];
        foreach ($files as $path) {
            $basename = basename($path, '.php');
            if (!preg_match('/^(\d{14})_(.+)$/', $basename, $m)) {
                continue;
            }
            $version    = $m[1];
            $legacyStem = $m[2];
            $legacyName = $legacyStem . '.php';

            // CamelCase → ClassName
            $parts = preg_split('/[_\-]/', $legacyStem) ?: [];
            $cc = '';
            foreach ($parts as $p) {
                $cc .= ucfirst(strtolower($p));
            }
            if ($cc === '' || ctype_digit($cc[0] ?? '')) {
                $cc = 'M' . $cc;
            }

            $mapping[$legacyName] = [$version, $cc];
        }
        return $mapping;
    }

    /**
     * Programmatischer Phinx-Aufruf via Symfony-Console.
     * Capturt Output, schreibt bei Fehler ins Log statt zu sterben.
     *
     * @return bool true wenn Phinx erfolgreich gelaufen ist (Exit 0), false sonst.
     *              Der Caller darf den File-Count-Cache nur bei true aktualisieren.
     */
    private function runPhinx(): bool
    {
        try {
            $app    = new PhinxApplication();
            $app->setAutoExit(false);
            $input  = new ArrayInput([
                'command'         => 'migrate',
                '--configuration' => $this->appRoot . '/phinx.php',
                '--environment'   => 'production',
            ]);
            $output = new BufferedOutput();
            $exit   = $app->run($input, $output);

            if ($exit !== 0) {
                \App\Logging\Logger::error("Phinx migrate exit code $exit\n" . $output->fetch());
                return false;
            }
            // Nur loggen wenn tatsächlich was passiert ist
            $text = $output->fetch();
            if (str_contains($text, 'migrating') || str_contains($text, 'migrated')) {
                \App\Logging\Logger::info("Phinx migrate output:\n" . $text);
            }
            return true;
        } catch (\Throwable $e) {
            \App\Logging\Logger::error("Phinx run failed: " . $e->getMessage());
            return false;
        }
    }
}
