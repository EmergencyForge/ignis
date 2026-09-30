<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use Exception;

/**
 * Sichert vor dem Kopieren jede Datei, die das Update überschreiben würde,
 * nach storage/backups/updates/backup_<Zeitstempel>. Zurückspielen geht von
 * Hand; _update-backup.json nennt dafür auch die neu angelegten Dateien.
 */
final class BackupManager
{
    private string $base;

    public function __construct(private readonly string $appRoot)
    {
        $this->base = $appRoot . '/storage/backups/updates';
    }

    /**
     * Legt das Backup-Basisverzeichnis an und liefert den Pfad für dieses
     * Backup. Angelegt wird es erst von backUp().
     */
    public function reserve(): string
    {
        if (!is_dir($this->base) && !mkdir($this->base, 0755, true)) {
            throw new Exception('Konnte Backup-Verzeichnis nicht erstellen: ' . $this->base);
        }

        return $this->base . '/backup_' . date('Y-m-d_H-i-s');
    }

    /**
     * @param list<string> $excludeDirs
     * @param list<string> $excludeFiles
     * @param string $versionFile wird mitgesichert, falls vorhanden
     * @return array{created_at:string, backed_up_files:int, created_files:list<string>}
     */
    public function backUp(string $backupDir, string $source, array $excludeDirs, array $excludeFiles, string $versionFile): array
    {
        if (!is_writable($this->base)) {
            throw new Exception('Keine Schreibberechtigung für Backup-Verzeichnis: ' . $this->base);
        }

        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Konnte Backup-Verzeichnis nicht erstellen: ' . $backupDir);
        }

        $summary = $this->copyOverwrittenFiles($source, $backupDir, $excludeDirs, $excludeFiles);

        if (file_exists($versionFile)) {
            if (!is_dir($backupDir . '/storage')) {
                mkdir($backupDir . '/storage', 0755, true);
            }
            copy($versionFile, $backupDir . '/storage/version.json');
        }

        return $summary;
    }

    /**
     * @param list<string> $excludeDirs
     * @param list<string> $excludeFiles
     * @return array{created_at:string, backed_up_files:int, created_files:list<string>}
     */
    private function copyOverwrittenFiles(string $source, string $backupDir, array $excludeDirs, array $excludeFiles): array
    {
        $sourceNormalized = realpath($source);
        if ($sourceNormalized === false) {
            throw new Exception('Update-Quelle für Backup nicht gefunden: ' . $source);
        }

        $backedUp = 0;
        $createdFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceNormalized, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $subPath = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceNormalized) + 1));
            if (FileInstaller::isExcluded($subPath, $excludeDirs, $excludeFiles)) {
                continue;
            }

            $currentPath = $this->appRoot . '/' . $subPath;
            if (!is_file($currentPath)) {
                $createdFiles[] = $subPath;
                continue;
            }

            $backupPath = $backupDir . '/' . $subPath;
            $backupParent = dirname($backupPath);
            if (!is_dir($backupParent) && !mkdir($backupParent, 0755, true)) {
                throw new Exception('Konnte Backup-Unterverzeichnis nicht erstellen: ' . $backupParent);
            }
            if (!copy($currentPath, $backupPath)) {
                throw new Exception('Konnte Datei nicht sichern: ' . $subPath);
            }
            $backedUp++;
        }

        $manifest = [
            'created_at' => date(DATE_ATOM),
            'backed_up_files' => $backedUp,
            'created_files' => $createdFiles,
        ];
        if (@file_put_contents(
            $backupDir . '/_update-backup.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        ) === false) {
            throw new Exception('Konnte Backup-Manifest nicht speichern.');
        }

        return $manifest;
    }
}
