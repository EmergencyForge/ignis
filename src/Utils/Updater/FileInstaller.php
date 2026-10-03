<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use App\Logging\Logger;
use Exception;

/**
 * Bringt die entpackten Dateien in die Installation: kopiert sie über den
 * Bestand, lässt Geschütztes aus und räumt ab, was das Update-Manifest
 * löschen will.
 */
final class FileInstaller
{
    public function __construct(private readonly string $appRoot)
    {
    }

    /**
     * Copy update files while excluding certain directories and files
     *
     * @param list<string> $excludeDirs Directories to completely skip
     * @param list<string> $excludeFiles Files to completely skip
     * @param list<string> $preserveDirs Directories where existing files should be preserved (only copy new files)
     */
    public function copy(string $source, array $excludeDirs, array $excludeFiles, array $preserveDirs = []): void
    {
        $dest = $this->appRoot;

        // Normalize source path with realpath to avoid path mismatches
        $sourceNormalized = realpath($source);
        if ($sourceNormalized === false) {
            throw new Exception('Source directory does not exist: ' . $source);
        }

        $dirIterator = new \RecursiveDirectoryIterator($sourceNormalized, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        $criticalFiles = ['composer.json', 'composer.lock'];
        $importantFiles = ['index.php', '.htaccess']; // Important but not critical - ensure they're overwritten
        $failedCriticalFiles = [];

        foreach ($iterator as $item) {
            // Get relative path from source directory
            // Use realpath for both paths to ensure they match exactly
            $itemPath = $item->getPathname();
            $itemRealPath = realpath($itemPath);

            // If realpath fails (shouldn't happen but be safe), use original path
            if ($itemRealPath === false) {
                $itemRealPath = $itemPath;
            }

            // Calculate relative path by removing the source directory prefix
            $subPath = substr($itemRealPath, strlen($sourceNormalized) + 1);
            $subPath = str_replace('\\', '/', $subPath); // Normalize to forward slashes

            if (self::isExcluded($subPath, $excludeDirs, $excludeFiles)) {
                continue;
            }

            $destPath = $dest . '/' . $subPath;

            // Check if path is in a preserve directory
            $inPreserveDir = false;
            foreach ($preserveDirs as $preserveDir) {
                $preserveDir = trim(str_replace('\\', '/', $preserveDir), '/');
                if ($subPath === $preserveDir || str_starts_with($subPath, $preserveDir . '/')) {
                    $inPreserveDir = true;
                    break;
                }
            }

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                // If in preserve directory, only copy if file doesn't exist
                if ($inPreserveDir) {
                    if (!file_exists($destPath)) {
                        if (!copy($item->getPathname(), $destPath)) {
                            throw new Exception('Konnte Datei nicht kopieren: ' . $subPath);
                        }
                    }
                } else {
                    // Normal behavior: overwrite existing files
                    // For critical and important files, ensure write permission and verify copy success
                    $isCriticalFile = in_array(basename($subPath), $criticalFiles) && dirname($subPath) === '.';
                    $isImportantFile = in_array(basename($subPath), $importantFiles) && dirname($subPath) === '.';

                    if (($isCriticalFile || $isImportantFile) && file_exists($destPath)) {
                        // Ensure file is writable before attempting to overwrite
                        if (!is_writable($destPath)) {
                            @chmod($destPath, 0644);
                            // chmod() leert den Stat-Cache schon selbst; der Aufruf
                            // sagt es PHPStan, das sonst das alte Ergebnis annimmt.
                            clearstatcache(true, $destPath);
                            // If still not writable, log warning but continue
                            if (!is_writable($destPath)) {
                                Logger::warning('Warning: Could not make file writable: ' . $destPath);
                            }
                        }
                    }

                    if (!copy($item->getPathname(), $destPath)) {
                        if ($isCriticalFile) {
                            $failedCriticalFiles[] = $subPath;
                        }
                        throw new Exception('Konnte Datei nicht kopieren: ' . $subPath);
                    }

                    // Verify critical files were actually updated
                    if ($isCriticalFile) {
                        if (filesize($destPath) !== filesize($item->getPathname())) {
                            $failedCriticalFiles[] = $subPath . ' (Größe stimmt nicht überein)';
                        }
                    }

                    // Log verification for important files (non-critical)
                    if ($isImportantFile && !$isCriticalFile) {
                        if (filesize($destPath) !== filesize($item->getPathname())) {
                            Logger::warning('Warning: Important file may not have been updated correctly: ' . $subPath);
                        }
                    }
                }
            }
        }

        // Report any critical file failures
        if (!empty($failedCriticalFiles)) {
            throw new Exception('Kritische Dateien konnten nicht aktualisiert werden: ' . implode(', ', $failedCriticalFiles));
        }
    }

    /**
     * Verarbeitet `update-manifest.json` aus dem Release-ZIP und löscht die
     * dort deklarierten Pfade aus dem Projekt-Root.
     *
     * Manifest-Format (im Root des ZIPs):
     *   {
     *     "version": "2.0.0",
     *     "delete_paths": ["enotf", "einsatz", "manv", ...]
     *   }
     *
     * Jeder Pfad wird strikt validiert:
     *   - kein Path-Traversal (`..`, Nullbytes, absolute Pfade)
     *   - geschützte Verzeichnisse (`storage`, `system`, `vendor`, `.git`,
     *     `.env*`, `public/index.php`) sind tabu
     *   - der Real-Pfad muss innerhalb des App-Roots liegen
     *
     * Fehlende Pfade werden als „skipped" ausgewiesen, nicht als Fehler.
     * Ein Kunde hat einen migrations-spezifischen Modul-Ordner evtl. schon
     * von Hand entfernt.
     *
     * @return array{applied:bool, deleted:array<int,string>, skipped:array<int,array{path:string,reason:string}>, error?:string}
     */
    public function applyManifest(string $sourceDir): array
    {
        $appRoot = $this->appRoot;
        $result = ['applied' => false, 'deleted' => [], 'skipped' => []];

        $manifestPath = $sourceDir . '/update-manifest.json';
        if (!is_file($manifestPath)) {
            return $result;
        }

        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            $result['error'] = 'update-manifest.json konnte nicht gelesen werden';
            return $result;
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            $result['error'] = 'update-manifest.json ist kein gültiges JSON';
            return $result;
        }

        $deletePaths = $manifest['delete_paths'] ?? [];
        if (!is_array($deletePaths)) {
            $result['error'] = 'delete_paths im Manifest ist kein Array';
            return $result;
        }

        $result['applied'] = true;

        foreach ($deletePaths as $entry) {
            if (!is_string($entry)) {
                $result['skipped'][] = ['path' => (string) $entry, 'reason' => 'kein String'];
                continue;
            }

            $normalized = $this->validateManifestDeletePath($entry);
            if ($normalized === null) {
                $result['skipped'][] = ['path' => $entry, 'reason' => 'geschützt/ungültig'];
                continue;
            }

            $fullPath = $appRoot . '/' . $normalized;
            if (!file_exists($fullPath) && !is_link($fullPath)) {
                $result['skipped'][] = ['path' => $normalized, 'reason' => 'nicht vorhanden'];
                continue;
            }

            try {
                if (is_dir($fullPath) && !is_link($fullPath)) {
                    self::deleteTree($fullPath);
                } else {
                    @unlink($fullPath);
                }
                $result['deleted'][] = $normalized;
            } catch (\Throwable $e) {
                $result['skipped'][] = ['path' => $normalized, 'reason' => 'Löschen fehlgeschlagen: ' . $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Validiert einen Pfad aus dem Update-Manifest und gibt ihn normalisiert
     * zurück, oder `null` wenn er nicht gelöscht werden darf.
     *
     * Reject-Regeln:
     *   - leer, `/`, `.`, `..`
     *   - enthält `..`, Nullbyte, Backslash-escaped Traversal
     *   - absolute Pfade (`/foo`, `C:\foo`)
     *   - geschützte Prefixe: `storage`, `system`, `vendor`, `.git`, `.env`, `public/index.php`
     *   - realpath-Check: Parent-Dir darf nicht außerhalb des App-Roots liegen
     */
    public function validateManifestDeletePath(string $path): ?string
    {
        $appRoot = $this->appRoot;
        $path = trim($path);
        if ($path === '' || $path === '/' || $path === '.' || $path === '..') {
            return null;
        }

        if (str_contains($path, "\0") || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[a-zA-Z]:#', $path)) {
            return null;
        }

        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '') {
            return null;
        }

        $protectedPrefixes = [
            'storage',
            'system',
            'vendor',
            '.git',
            '.env',
            'public/index.php',
            'composer.json',
            'composer.lock',
            '.htaccess',
        ];
        foreach ($protectedPrefixes as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) {
                return null;
            }
        }

        // Realpath-Check: Parent muss innerhalb des App-Roots liegen
        $fullPath   = $appRoot . '/' . $normalized;
        $parentDir  = dirname($fullPath);
        $resolvedParent = realpath($parentDir);
        $resolvedRoot   = realpath($appRoot);
        if ($resolvedParent === false || $resolvedRoot === false) {
            return null;
        }
        $resolvedRootWithSep = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolvedParent . DIRECTORY_SEPARATOR, $resolvedRootWithSep)
            && $resolvedParent !== $resolvedRoot) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param list<string> $excludeDirs
     * @param list<string> $excludeFiles
     */
    public static function isExcluded(string $subPath, array $excludeDirs, array $excludeFiles): bool
    {
        foreach ($excludeDirs as $excludeDir) {
            $excludeDir = trim(str_replace('\\', '/', $excludeDir), '/');
            if ($subPath === $excludeDir || str_starts_with($subPath, $excludeDir . '/')) {
                return true;
            }
        }

        foreach ($excludeFiles as $excludeFile) {
            if ($subPath === $excludeFile || basename($subPath) === $excludeFile) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively delete directory
     */
    public static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
