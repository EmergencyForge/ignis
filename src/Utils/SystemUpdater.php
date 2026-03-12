<?php

namespace App\Utils;

use Exception;

/**
 * SystemUpdater
 * 
 * Handles system update operations including checking for updates,
 * downloading updates from GitHub releases, and applying them.
 */
class SystemUpdater
{
    private string $versionFile;
    private string $composerPendingFile;
    private string $githubRepo = 'EmergencyForge/intraRP';
    private string $githubApiUrl;
    private array $currentVersion;
    private array $diagnosticLog = [];
    private string $diagnosticFile;

    public function __construct()
    {
        $appRoot = dirname(dirname(__DIR__));
        $this->versionFile = $appRoot . '/system/updates/version.json';
        $this->composerPendingFile = $appRoot . '/system/updates/composer_pending.json';
        $this->diagnosticFile = $appRoot . '/system/updates/diagnostic.log';
        $this->githubApiUrl = "https://api.github.com/repos/{$this->githubRepo}";
        $this->loadCurrentVersion();
        $this->cleanupOldTempDirectories();
    }

    /**
     * Load current version from version.json
     */
    private function loadCurrentVersion(): void
    {
        if (!file_exists($this->versionFile)) {
            $this->currentVersion = [
                'version' => 'v0.5.0',
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => '0',
                'commit_hash' => 'initial'
            ];
            return;
        }

        $content = file_get_contents($this->versionFile);
        $this->currentVersion = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse version.json: ' . json_last_error_msg());
        }
    }

    /**
     * Get current version information
     */
    public function getCurrentVersion(): array
    {
        return $this->currentVersion;
    }

    /**
     * Check for available updates from GitHub releases
     * 
     * @param bool $includePreRelease If true, include pre-release versions in the check
     */
    public function checkForUpdates(?bool $includePreRelease = null): array
    {
        try {
            // If not explicitly set, use current version's pre-release status
            if ($includePreRelease === null) {
                $includePreRelease = $this->isPreRelease();
            }

            $latestRelease = $this->fetchLatestRelease($includePreRelease);

            if (!$latestRelease) {
                return [
                    'available' => false,
                    'error' => true,
                    'message' => 'Konnte nicht auf GitHub-API zugreifen. Bitte prüfen Sie Ihre Internetverbindung oder versuchen Sie es später erneut (möglicherweise API-Ratenlimit erreicht).'
                ];
            }

            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->currentVersion['version'];

            $isNewer = $this->compareVersions($latestVersion, $currentVersion);
            $isLatestPreRelease = $latestRelease['prerelease'] ?? false;

            return [
                'available' => $isNewer,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_name' => $latestRelease['name'] ?? $latestVersion,
                'release_notes' => $latestRelease['body'] ?? 'Keine Release-Notizen verfügbar.',
                'published_at' => $latestRelease['published_at'] ?? null,
                'download_url' => $latestRelease['zipball_url'] ?? null,
                'html_url' => $latestRelease['html_url'] ?? null,
                'is_prerelease' => $isLatestPreRelease
            ];
        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => true,
                'message' => 'Fehler beim Prüfen auf Updates: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch latest release from GitHub API
     * 
     * @param bool $includePreRelease If true, include pre-release versions
     */
    private function fetchLatestRelease(bool $includePreRelease = false): ?array
    {
        // Always fetch from list to get both stable and pre-release versions
        return $this->fetchLatestReleaseFromList($includePreRelease);
    }

    /**
     * Fetch latest release from releases list
     * 
     * @param bool $includePreRelease If true, returns latest release (can be pre-release or stable).
     *                                 If false, returns latest stable release only.
     */
    private function fetchLatestReleaseFromList(bool $includePreRelease = false): ?array
    {
        $url = "{$this->githubApiUrl}/releases?per_page=20";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: intraRP-Updater',
                    'Accept: application/vnd.github+json'
                ],
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $releases = json_decode($response, true);

        if (!is_array($releases) || empty($releases)) {
            return null;
        }

        // Filter out draft releases
        $releases = array_filter($releases, function ($release) {
            return !($release['draft'] ?? false);
        });

        if (empty($releases)) {
            return null;
        }

        // If including pre-releases, return the first (latest) non-draft release
        // This can be either a pre-release or stable version
        if ($includePreRelease) {
            return reset($releases);
        }

        // Otherwise, find the latest stable (non-prerelease) release
        foreach ($releases as $release) {
            if (!($release['prerelease'] ?? false)) {
                return $release;
            }
        }

        // If no stable release found, return the first release
        return reset($releases);
    }

    /**
     * Compare two version strings
     * Returns true if $version1 is newer than $version2
     */
    private function compareVersions(string $version1, string $version2): bool
    {
        // Remove 'v' prefix if present
        $v1 = ltrim($version1, 'v');
        $v2 = ltrim($version2, 'v');

        return version_compare($v1, $v2, '>');
    }

    /**
     * Download and apply update
     * 
     * @param string $downloadUrl URL to download the update from
     * @param string $newVersion Version being installed
     * @param bool $isPreRelease Whether the new version is a pre-release
     * @return array Result of the update operation
     */
    public function downloadAndApplyUpdate(string $downloadUrl, string $newVersion, bool $isPreRelease = false): array
    {
        try {
            // Security: Validate download URL is from GitHub
            if (!preg_match('#^https://api\.github\.com/repos/' . preg_quote($this->githubRepo, '#') . '/zipball/#', $downloadUrl)) {
                throw new Exception('Ungültige Download-URL. Updates können nur von GitHub heruntergeladen werden.');
            }

            // Security: Validate version format
            // Allow up to 5 version segments (e.g., v0.5.4.3.1) plus optional pre-release suffix
            // Also allow dev-branch-commit format for branch updates (e.g., dev-main-abc12345)
            // Note: {0,4} means 0 to 4 additional segments after the first, totaling 1 to 5 segments
            if (!preg_match('/^v?\d+(\.\d+){0,4}(-[a-zA-Z0-9.-]+)?$/', $newVersion) &&
                !preg_match('/^dev-[a-zA-Z0-9._\/-]+-[a-f0-9]{7,8}$/', $newVersion)) {
                throw new Exception('Ungültiges Versionsformat.');
            }

            $appRoot = dirname(dirname(__DIR__));

            // Check write permissions
            if (!is_writable($appRoot)) {
                throw new Exception('Keine Schreibberechtigung für das Anwendungsverzeichnis. Bitte Dateiberechtigungen prüfen.');
            }

            // Check disk space before starting update
            $freeSpaceApp = disk_free_space($appRoot);
            $requiredSpace = 200 * 1024 * 1024; // 200 MB minimum

            if ($freeSpaceApp === false || $freeSpaceApp < $requiredSpace) {
                $availableMB = $freeSpaceApp !== false ? round($freeSpaceApp / 1024 / 1024, 2) : 0;
                throw new Exception("Nicht genügend Speicherplatz im Anwendungsverzeichnis. Benötigt: 200 MB, Verfügbar: {$availableMB} MB");
            }

            // Use local temp directory for Plesk/Shared hosting compatibility
            // sys_get_temp_dir() is often not accessible in Plesk environments
            $tempDirBase = $appRoot . '/storage/temp';
            if (!is_dir($tempDirBase)) {
                if (!mkdir($tempDirBase, 0755, true)) {
                    throw new Exception('Konnte temporäres Basisverzeichnis nicht erstellen: ' . $tempDirBase);
                }
            }

            if (!is_writable($tempDirBase)) {
                throw new Exception('Temporäres Basisverzeichnis ist nicht beschreibbar: ' . $tempDirBase . '. Bitte Berechtigungen prüfen.');
            }

            // Create temporary directory for this update
            $tempDir = $tempDirBase . '/update_' . bin2hex(random_bytes(8));
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception('Konnte temporäres Verzeichnis nicht erstellen: ' . $tempDir);
            }

            // Verify the temporary directory is writable
            if (!is_writable($tempDir)) {
                throw new Exception('Temporäres Verzeichnis ist nicht beschreibbar: ' . $tempDir . '. Bitte Berechtigungen prüfen.');
            }

            $zipFile = $tempDir . '/update.zip';
            $extractDir = $tempDir . '/extracted';

            // Step 1: Download update
            // Note: GitHub's zipball_url redirects, so we need to follow location
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater',
                        'Accept: application/zip, application/octet-stream'
                    ],
                    'timeout' => 300,
                    'follow_location' => 1,
                    'max_redirects' => 5
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ]);

            $updateContent = @file_get_contents($downloadUrl, false, $context);

            if ($updateContent === false) {
                throw new Exception('Fehler beim Herunterladen des Updates von: ' . $downloadUrl . '. Bitte Internetverbindung prüfen.');
            }

            // Check if download has content
            $downloadSize = strlen($updateContent);
            if ($downloadSize === 0) {
                throw new Exception('Download war erfolgreich, aber die Datei ist leer (0 Bytes). URL: ' . $downloadUrl);
            }

            // Verify it looks like a ZIP file (starts with PK)
            if (substr($updateContent, 0, 2) !== 'PK') {
                // It might be an error message or HTML page
                $preview = substr($updateContent, 0, 200);
                throw new Exception('Download ist keine gültige ZIP-Datei. Möglicherweise API-Fehler oder Berechtigung fehlt. Inhalt-Start: ' . htmlspecialchars($preview));
            }

            $bytesWritten = @file_put_contents($zipFile, $updateContent);
            if ($bytesWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unbekannter Fehler';
                throw new Exception('Konnte Update-Datei nicht speichern in: ' . $zipFile . '. Fehler: ' . $errorMsg . '. Bitte Speicherplatz und Berechtigungen prüfen.');
            }

            if ($bytesWritten === 0 && $downloadSize > 0) {
                throw new Exception('Update-Datei wurde mit 0 Bytes geschrieben. Möglicherweise fehlt Speicherplatz oder Schreibrechte für: ' . $zipFile);
            }

            // Step 2: Extract ZIP
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive PHP-Erweiterung nicht verfügbar. Bitte installieren Sie php-zip.');
            }

            // Validate ZIP file exists and has content
            if (!file_exists($zipFile)) {
                throw new Exception('ZIP-Datei wurde nicht gefunden: ' . $zipFile);
            }

            $zipSize = filesize($zipFile);
            if ($zipSize === false || $zipSize === 0) {
                throw new Exception('ZIP-Datei ist leer oder konnte nicht gelesen werden. Größe: ' . ($zipSize === false ? 'unbekannt' : '0 Bytes'));
            }

            $zip = new \ZipArchive();
            $openResult = $zip->open($zipFile);
            if ($openResult !== true) {
                $errorMessages = [
                    \ZipArchive::ER_EXISTS => 'Datei existiert bereits',
                    \ZipArchive::ER_INCONS => 'ZIP-Archiv ist inkonsistent',
                    \ZipArchive::ER_INVAL => 'Ungültiges Argument',
                    \ZipArchive::ER_MEMORY => 'Speicherfehler',
                    \ZipArchive::ER_NOENT => 'Datei existiert nicht',
                    \ZipArchive::ER_NOZIP => 'Keine gültige ZIP-Datei',
                    \ZipArchive::ER_OPEN => 'Datei konnte nicht geöffnet werden',
                    \ZipArchive::ER_READ => 'Lesefehler',
                    \ZipArchive::ER_SEEK => 'Seek-Fehler'
                ];
                $errorMsg = $errorMessages[$openResult] ?? 'Unbekannter Fehler (' . $openResult . ')';
                throw new Exception('Konnte ZIP-Datei nicht öffnen: ' . $errorMsg . '. Dateigröße: ' . round($zipSize / 1024, 2) . ' KB');
            }

            $numFiles = $zip->numFiles;
            if ($numFiles === 0) {
                $zip->close();
                throw new Exception('ZIP-Datei enthält keine Dateien. Möglicherweise ist das Update beschädigt.');
            }

            // Create extraction directory before extracting
            if (!is_dir($extractDir)) {
                if (!mkdir($extractDir, 0755, true)) {
                    $zip->close();
                    throw new Exception('Konnte Extraktions-Verzeichnis nicht erstellen: ' . $extractDir);
                }
            }

            if (!is_writable($extractDir)) {
                $zip->close();
                throw new Exception('Extraktions-Verzeichnis ist nicht beschreibbar: ' . $extractDir);
            }

            $extractResult = $zip->extractTo($extractDir);
            $zip->close();

            if (!$extractResult) {
                throw new Exception('Konnte ZIP-Datei nicht extrahieren (' . $numFiles . ' Dateien). Bitte Speicherplatz und Berechtigungen prüfen.');
            }

            // Give filesystem time to sync (especially on Windows/Plesk)
            clearstatcache();
            usleep(100000); // 100ms wait

            // GitHub zipballs extract to a subdirectory like "EmergencyForge-intraRP-abc123/"
            $extractedDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            if (empty($extractedDirs)) {
                // Debug: List all extracted files/folders
                $allItems = glob($extractDir . '/*');
                $itemsList = $allItems ? implode(', ', array_map('basename', $allItems)) : 'keine';

                // Additional check with scandir
                $scannedItems = @scandir($extractDir);
                $scannedFiltered = $scannedItems ? array_diff($scannedItems, ['.', '..']) : [];
                $scannedList = !empty($scannedFiltered) ? implode(', ', $scannedFiltered) : 'keine';

                throw new Exception('Keine extrahierten Verzeichnisse gefunden. ZIP sollte ' . $numFiles . ' Dateien enthalten. Glob-Items: ' . $itemsList . '. Scandir-Items: ' . $scannedList . '. Extract-Dir: ' . $extractDir);
            }
            $sourceDir = $extractedDirs[0];

            // Step 3: Create backup
            $backupDir = $appRoot . '/system/updates/backup_' . date('Y-m-d_H-i-s');
            if (!is_writable(dirname($backupDir))) {
                throw new Exception('Keine Schreibberechtigung für Backup-Verzeichnis: ' . dirname($backupDir));
            }

            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Konnte Backup-Verzeichnis nicht erstellen: ' . $backupDir);
            }

            $filesToBackup = ['.htaccess', 'index.php', 'composer.json', 'composer.lock'];
            $dirsToBackup = ['src', 'assets', 'api'];

            foreach ($filesToBackup as $file) {
                if (file_exists($appRoot . '/' . $file)) {
                    if (!copy($appRoot . '/' . $file, $backupDir . '/' . $file)) {
                        throw new Exception('Konnte Datei nicht sichern: ' . $file);
                    }
                }
            }

            foreach ($dirsToBackup as $dir) {
                if (is_dir($appRoot . '/' . $dir)) {
                    try {
                        $this->recursiveCopy($appRoot . '/' . $dir, $backupDir . '/' . $dir);
                    } catch (Exception $e) {
                        throw new Exception('Konnte Verzeichnis nicht sichern: ' . $dir . ' - ' . $e->getMessage());
                    }
                }
            }

            // Backup only version.json from system directory (not the whole system/updates)
            if (!is_dir($backupDir . '/system')) {
                mkdir($backupDir . '/system', 0755, true);
            }
            if (file_exists($appRoot . '/system/updates/version.json')) {
                if (!is_dir($backupDir . '/system/updates')) {
                    mkdir($backupDir . '/system/updates', 0755, true);
                }
                copy($appRoot . '/system/updates/version.json', $backupDir . '/system/updates/version.json');
            }

            // Step 4: Apply update (copy files)
            // Exclude vendor, storage, and system/updates directories
            $excludeDirs = ['vendor', 'storage', 'system/updates'];
            $excludeFiles = ['.env', '.git', '.gitignore'];
            // For these directories, only copy new files (don't overwrite existing customizations)
            $preserveDirs = ['assets/img'];

            try {
                $this->copyUpdateFiles($sourceDir, $appRoot, $excludeDirs, $excludeFiles, $preserveDirs);
            } catch (Exception $e) {
                throw new Exception('Fehler beim Kopieren der Update-Dateien: ' . $e->getMessage() . ' - Backup verfügbar in: ' . $backupDir);
            }

            // Step 5: Update version.json
            if (!$this->updateVersionFile([
                'version' => $newVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->currentVersion['build_number'] ?? 0) + 1,
                'commit_hash' => 'auto-update',
                'prerelease' => $isPreRelease
            ])) {
                throw new Exception('Konnte version.json nicht aktualisieren. Update möglicherweise unvollständig.');
            }

            // Step 6: Mark that composer needs to run
            // Don't run composer immediately to avoid dependency issues with the current page load
            $composerStatus = [
                'pending' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'version' => $newVersion
            ];

            $dir = dirname($this->composerPendingFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!file_put_contents($this->composerPendingFile, json_encode($composerStatus, JSON_PRETTY_PRINT))) {
                // Non-critical: composer will need manual installation
                \App\Logging\Logger::warning('Warning: Could not write composer pending file: ' . $this->composerPendingFile);
            }

            // Step 7: Clear cache
            $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }

            // Clean up temp files
            $this->recursiveDelete($tempDir);

            return [
                'success' => true,
                'message' => 'Update erfolgreich installiert! Composer-Abhängigkeiten werden jetzt aktualisiert...',
                'version' => $newVersion,
                'backup_dir' => $backupDir,
                'composer_pending' => true
            ];
        } catch (Exception $e) {
            // Clean up temp files if they exist
            if (isset($tempDir) && is_dir($tempDir)) {
                try {
                    $this->recursiveDelete($tempDir);
                } catch (Exception $cleanupEx) {
                    // Ignore cleanup errors
                }
            }

            // Run comprehensive diagnostics
            $diagnostics = $this->runUpdateDiagnostics($e, [
                'operation' => 'downloadAndApplyUpdate',
                'download_url' => $downloadUrl,
                'new_version' => $newVersion,
                'temp_dir' => $tempDir ?? null,
                'backup_dir' => $backupDir ?? null
            ]);

            return [
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Update: ' . $e->getMessage(),
                'diagnostics' => $diagnostics,
                'diagnostic_summary' => $this->formatDiagnosticSummary($diagnostics),
                'diagnostic_html' => $this->formatDiagnosticHTML($diagnostics),
                'diagnostic_support' => $this->formatDiagnosticForSupport($diagnostics)
            ];
        }
    }

    /**
     * Recursively copy directory
     */
    private function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        // Normalize source path with realpath to avoid path mismatches
        $sourceNormalized = realpath($source);
        if ($sourceNormalized === false) {
            throw new Exception('Source directory does not exist: ' . $source);
        }

        $dirIterator = new \RecursiveDirectoryIterator($sourceNormalized, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $itemRealPath = realpath($itemPath);

            // If realpath fails (shouldn't happen but be safe), use original path
            if ($itemRealPath === false) {
                $itemRealPath = $itemPath;
            }

            // Calculate relative path by removing the source directory prefix
            $subPath = substr($itemRealPath, strlen($sourceNormalized) + 1);
            $subPath = str_replace('\\', '/', $subPath);
            $destPath = $dest . '/' . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }

    /**
     * Copy update files while excluding certain directories and files
     * 
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @param array $excludeDirs Directories to completely skip
     * @param array $excludeFiles Files to completely skip
     * @param array $preserveDirs Directories where existing files should be preserved (only copy new files)
     */
    private function copyUpdateFiles(string $source, string $dest, array $excludeDirs, array $excludeFiles, array $preserveDirs = []): void
    {
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

            // Check if path contains excluded directory
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($subPath, $excludeDir) === 0) {
                    $skip = true;
                    break;
                }
            }

            // Check if file is excluded
            foreach ($excludeFiles as $excludeFile) {
                if ($subPath === $excludeFile || basename($subPath) === $excludeFile) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $destPath = $dest . '/' . $subPath;

            // Check if path is in a preserve directory
            $inPreserveDir = false;
            foreach ($preserveDirs as $preserveDir) {
                if (strpos($subPath, $preserveDir) === 0) {
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
                        if (!copy($item, $destPath)) {
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
                            // If still not writable, log warning but continue
                            if (!is_writable($destPath)) {
                                \App\Logging\Logger::warning('Warning: Could not make file writable: ' . $destPath);
                            }
                        }
                    }

                    if (!copy($item, $destPath)) {
                        if ($isCriticalFile) {
                            $failedCriticalFiles[] = $subPath;
                        }
                        throw new Exception('Konnte Datei nicht kopieren: ' . $subPath);
                    }

                    // Verify critical files were actually updated
                    if ($isCriticalFile) {
                        if (filesize($destPath) !== filesize($item)) {
                            $failedCriticalFiles[] = $subPath . ' (Größe stimmt nicht überein)';
                        }
                    }

                    // Log verification for important files (non-critical)
                    if ($isImportantFile && !$isCriticalFile) {
                        if (filesize($destPath) !== filesize($item)) {
                            \App\Logging\Logger::warning('Warning: Important file may not have been updated correctly: ' . $subPath);
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
     * Recursively delete directory
     */
    private function recursiveDelete(string $dir): void
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
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($dir);
    }

    /**
     * Run composer install after system update
     * 
     * @param string $appRoot Application root directory
     * @return array Result containing execution status and output
     */
    private function runComposerInstall(string $appRoot): array
    {
        // Check if composer is available
        $composerPath = $this->findComposerExecutable();

        if (!$composerPath) {
            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'Composer-Executable nicht gefunden.'
            ];
        }

        try {
            // OS detection for proper command syntax
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            // Use composer's --working-dir option for safer execution
            // Windows doesn't have timeout command, so omit it there
            if ($isWindows) {
                $command = sprintf(
                    '%s install --working-dir=%s --no-dev --optimize-autoloader --no-interaction 2>&1',
                    escapeshellarg($composerPath),
                    escapeshellarg($appRoot)
                );
            } else {
                $command = sprintf(
                    'timeout 600 %s install --working-dir=%s --no-dev --optimize-autoloader --no-interaction 2>&1',
                    escapeshellarg($composerPath),
                    escapeshellarg($appRoot)
                );
            }

            // Execute composer command with timeout
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            $outputString = implode("\n", $output);

            // Check if timeout occurred (exit code 124)
            if ($returnCode === 124) {
                return [
                    'executed' => true,
                    'success' => false,
                    'message' => 'Composer-Installation hat zu lange gedauert (Timeout nach 10 Minuten).',
                    'output' => $outputString,
                    'return_code' => $returnCode
                ];
            }

            if ($returnCode === 0) {
                return [
                    'executed' => true,
                    'success' => true,
                    'message' => 'Composer-Abhängigkeiten erfolgreich installiert.',
                    'output' => $outputString
                ];
            } else {
                // Run diagnostics for composer failure
                $diagnostics = $this->runUpdateDiagnostics(
                    new Exception('Composer-Installation fehlgeschlagen mit Exit-Code ' . $returnCode),
                    [
                        'operation' => 'composer_install',
                        'return_code' => $returnCode,
                        'output' => $outputString,
                        'composer_path' => $composerPath
                    ]
                );

                return [
                    'executed' => true,
                    'success' => false,
                    'error' => true,
                    'message' => 'Composer-Installation fehlgeschlagen.',
                    'output' => $outputString,
                    'return_code' => $returnCode,
                    'diagnostics' => $diagnostics,
                    'diagnostic_summary' => $this->formatDiagnosticSummary($diagnostics),
                    'diagnostic_html' => $this->formatDiagnosticHTML($diagnostics),
                    'diagnostic_support' => $this->formatDiagnosticForSupport($diagnostics)
                ];
            }
        } catch (Exception $e) {
            // Run diagnostics for exception
            $diagnostics = $this->runUpdateDiagnostics($e, [
                'operation' => 'composer_install_exception',
                'composer_path' => $composerPath
            ]);

            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Ausführen von Composer: ' . $e->getMessage(),
                'diagnostics' => $diagnostics,
                'diagnostic_summary' => $this->formatDiagnosticSummary($diagnostics),
                'diagnostic_html' => $this->formatDiagnosticHTML($diagnostics),
                'diagnostic_support' => $this->formatDiagnosticForSupport($diagnostics)
            ];
        }
    }

    /**
     * Find composer executable on the system
     * 
     * @return string|null Path to composer executable or null if not found
     */
    private function findComposerExecutable(): ?string
    {
        // Try absolute paths first using is_executable for security
        $absolutePaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer'
        ];

        foreach ($absolutePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // For composer in PATH, use which/where command with strict validation
        $pathNames = ['composer', 'composer.phar'];
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        foreach ($pathNames as $name) {
            // Strict validation: only alphanumeric, underscore, hyphen
            // Single dot allowed only for .phar extension at the end
            if (preg_match('/^[a-zA-Z0-9_-]+(\\.phar)?$/', $name)) {
                $output = [];
                $returnCode = 0;

                // Use correct command for OS
                $command = $isWindows
                    ? 'where ' . escapeshellarg($name) . ' 2>NUL'
                    : 'which ' . escapeshellarg($name) . ' 2>/dev/null';

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && !empty($output)) {
                    $execPath = trim($output[0]);

                    // Use realpath to resolve any symlinks and path traversal
                    $realPath = realpath($execPath);

                    // Verify it's a real file, executable, and in safe directories
                    if ($realPath && file_exists($realPath) && is_executable($realPath)) {
                        if ($isWindows) {
                            // Windows: Check for common composer installation paths
                            $safePaths = ['C:\\ProgramData\\ComposerSetup\\', 'C:\\composer\\', 'C:\\tools\\'];
                            foreach ($safePaths as $safePath) {
                                if (stripos($realPath, $safePath) === 0) {
                                    return $realPath;
                                }
                            }
                        } else {
                            // Unix/Linux: Only allow paths in standard bin directories
                            $safePaths = ['/usr/local/bin/', '/usr/bin/', '/bin/'];
                            foreach ($safePaths as $safePath) {
                                if (strpos($realPath, $safePath) === 0) {
                                    return $realPath;
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if composer installation is pending
     * 
     * @return array Status information
     */
    public function getComposerStatus(): array
    {
        if (!file_exists($this->composerPendingFile)) {
            return [
                'pending' => false,
                'message' => 'Keine ausstehende Composer-Installation.'
            ];
        }

        $content = file_get_contents($this->composerPendingFile);
        $status = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Corrupted file, remove it and return not pending
            if (file_exists($this->composerPendingFile) && !unlink($this->composerPendingFile)) {
                \App\Logging\Logger::warning('Warning: Could not remove corrupted composer pending file: ' . $this->composerPendingFile);
            }
            return [
                'pending' => false,
                'error' => true,
                'message' => 'Composer-Status-Datei war beschädigt und wurde entfernt.'
            ];
        }

        return array_merge(['pending' => true], $status ?? []);
    }

    /**
     * Execute pending composer installation
     * 
     * @return array Result of composer execution
     */
    public function executePendingComposerInstall(): array
    {
        if (!file_exists($this->composerPendingFile)) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Keine ausstehende Composer-Installation gefunden.'
            ];
        }

        $appRoot = dirname(dirname(__DIR__));

        // Run composer install
        $result = $this->runComposerInstall($appRoot);

        // Remove pending status file if successful
        if ($result['success']) {
            if (file_exists($this->composerPendingFile) && !unlink($this->composerPendingFile)) {
                \App\Logging\Logger::warning('Warning: Could not remove composer pending file after successful install: ' . $this->composerPendingFile);
            }
        }

        return $result;
    }

    /**
     * Update version.json file
     * 
     * @param array $versionData New version data
     */
    public function updateVersionFile(array $versionData): bool
    {
        try {
            $json = json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $dir = dirname($this->versionFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($this->versionFile, $json);
            $this->currentVersion = $versionData;

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all available releases from GitHub
     * 
     * @param int $limit Maximum number of releases to fetch
     */
    public function getAllReleases(int $limit = 10): array
    {
        try {
            $url = "{$this->githubApiUrl}/releases?per_page={$limit}";

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater',
                        'Accept: application/vnd.github+json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return [];
            }

            $releases = json_decode($response, true);

            return $releases ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if current version is a pre-release (beta, alpha, rc)
     * First checks the prerelease flag in version.json, then falls back to version string pattern matching
     */
    public function isPreRelease(): bool
    {
        // Check if version.json has an explicit prerelease flag
        if (isset($this->currentVersion['prerelease'])) {
            return (bool)$this->currentVersion['prerelease'];
        }

        // Fall back to pattern matching in version string
        $version = $this->currentVersion['version'];
        return preg_match('/(alpha|beta|rc|dev)/i', $version) === 1;
    }

    /**
     * Check if a specific version string is a pre-release
     * 
     * @param string $version Version string to check
     * @return bool True if version is a pre-release
     */
    public function isVersionPreRelease(string $version): bool
    {
        return preg_match('/(alpha|beta|rc|dev)/i', $version) === 1;
    }

    /**
     * Get version age in days
     */
    public function getVersionAge(): int
    {
        if (!isset($this->currentVersion['updated_at'])) {
            return 0;
        }

        $updatedAt = strtotime($this->currentVersion['updated_at']);
        $now = time();

        return (int) floor(($now - $updatedAt) / 86400);
    }

    /**
     * Check if update is recommended based on version age
     */
    public function isUpdateRecommended(): bool
    {
        $age = $this->getVersionAge();

        // Recommend update if version is older than 90 days
        return $age > 90;
    }

    /**
     * Get update urgency level
     * Returns: 'none', 'low', 'medium', 'high', 'critical'
     */
    public function getUpdateUrgency(): string
    {
        $updateInfo = $this->checkForUpdates();

        if (!$updateInfo['available']) {
            return 'none';
        }

        $age = $this->getVersionAge();
        $currentVersion = ltrim($this->currentVersion['version'], 'v');
        $latestVersion = ltrim($updateInfo['latest_version'], 'v');

        // Parse versions
        $currentParts = explode('.', $currentVersion);
        $latestParts = explode('.', $latestVersion);

        // Major version change = high urgency
        if (($latestParts[0] ?? 0) > ($currentParts[0] ?? 0)) {
            return 'high';
        }

        // Minor version change with old version = medium urgency
        if (($latestParts[1] ?? 0) > ($currentParts[1] ?? 0)) {
            return $age > 60 ? 'medium' : 'low';
        }

        // Patch version change
        if (($latestParts[2] ?? 0) > ($currentParts[2] ?? 0)) {
            return $age > 30 ? 'medium' : 'low';
        }

        return 'low';
    }

    /**
     * Get formatted release notes as HTML
     */
    public function getFormattedReleaseNotes(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $output = '';
        $inList = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Headers
            if (preg_match('/^### (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h6>' . htmlspecialchars($matches[1]) . '</h6>';
            } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h5>' . htmlspecialchars($matches[1]) . '</h5>';
            } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h4>' . htmlspecialchars($matches[1]) . '</h4>';
            }
            // List items
            elseif (preg_match('/^[\*\-] (.+)$/', $line, $matches)) {
                if (!$inList) {
                    $output .= '<ul>';
                    $inList = true;
                }
                $output .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
            }
            // Bold text
            elseif (preg_match('/\*\*(.+?)\*\*/', $line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                // First escape entire line, then replace markdown markers with HTML
                $line = htmlspecialchars($line);
                $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
                $output .= '<p>' . $line . '</p>';
            }
            // Regular text
            elseif (!empty($line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<p>' . htmlspecialchars($line) . '</p>';
            }
        }

        if ($inList) {
            $output .= '</ul>';
        }

        return $output;
    }

    /**
     * Cache update check results to avoid rate limiting
     */
    private function getCachedUpdateCheck(): ?array
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = json_decode(file_get_contents($cacheFile), true);

        if (!$cacheData || !isset($cacheData['timestamp'])) {
            return null;
        }

        // Cache valid for 1 hour
        if (time() - $cacheData['timestamp'] > 3600) {
            return null;
        }

        // Invalidate cache if current version has changed
        // This ensures users see accurate update notifications after local upgrades
        $cachedVersion = $cacheData['current_version'] ?? null;
        $actualVersion = $this->currentVersion['version'] ?? null;

        if ($cachedVersion !== null && $actualVersion !== null && $cachedVersion !== $actualVersion) {
            return null;
        }

        return $cacheData['data'] ?? null;
    }

    /**
     * Save update check results to cache
     */
    private function cacheUpdateCheck(array $data): void
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';

        $cacheData = [
            'timestamp' => time(),
            'current_version' => $this->currentVersion['version'] ?? 'unknown',
            'data' => $data
        ];

        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * Check for updates with caching support
     * 
     * @param bool $forceRefresh If true, bypass cache and fetch fresh data
     * @param bool $includePreRelease If true, include pre-release versions in the check
     */
    public function checkForUpdatesCached(bool $forceRefresh = false, ?bool $includePreRelease = null): array
    {
        if (!$forceRefresh) {
            $cached = $this->getCachedUpdateCheck();

            if ($cached !== null) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $result = $this->checkForUpdates($includePreRelease);
        $this->cacheUpdateCheck($result);
        $result['cached'] = false;

        return $result;
    }

    /**
     * Clear the update check cache
     */
    public function clearCache(): bool
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';

        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }

        return true;
    }

    /**
     * Fetch all branches from GitHub API
     *
     * @return array List of branch names
     */
    public function fetchBranches(): array
    {
        try {
            $url = "{$this->githubApiUrl}/branches?per_page=100";

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater',
                        'Accept: application/vnd.github+json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return [];
            }

            $branches = json_decode($response, true);

            if (!is_array($branches)) {
                return [];
            }

            return $branches;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch the latest commit of a specific branch from GitHub API
     *
     * @param string $branch Branch name
     * @return array|null Commit info or null on error
     */
    public function fetchBranchLatestCommit(string $branch): ?array
    {
        try {
            $url = "{$this->githubApiUrl}/commits/" . urlencode($branch);

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater',
                        'Accept: application/vnd.github+json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return null;
            }

            $commit = json_decode($response, true);

            if (!is_array($commit) || !isset($commit['sha'])) {
                return null;
            }

            return $commit;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Download and apply update from a specific branch commit
     *
     * @param string $branch Branch name
     * @param string $commitSha Full commit SHA
     * @return array Result of the update operation
     */
    public function downloadAndApplyBranchUpdate(string $branch, string $commitSha): array
    {
        // Construct the zipball URL for the specific commit
        $downloadUrl = "https://api.github.com/repos/{$this->githubRepo}/zipball/{$commitSha}";

        // Use a dev version string: branch-shortsha
        $shortSha = substr($commitSha, 0, 8);
        $devVersion = "dev-{$branch}-{$shortSha}";

        $result = $this->downloadAndApplyUpdate($downloadUrl, $devVersion, true);

        // Update version.json with the full commit hash for proper detection
        if ($result['success']) {
            $this->updateVersionFile([
                'version' => $devVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->currentVersion['build_number'] ?? 0) + 1,
                'commit_hash' => $commitSha,
                'prerelease' => true,
                'branch' => $branch
            ]);
        }

        return $result;
    }

    /**
     * Clean up old temporary directories from storage/temp
     * Removes directories older than 24 hours
     */
    private function cleanupOldTempDirectories(): void
    {
        try {
            $appRoot = dirname(dirname(__DIR__));
            $tempBase = $appRoot . '/storage/temp';

            if (!is_dir($tempBase)) {
                return;
            }

            $maxAge = 24 * 3600; // 24 hours in seconds
            $now = time();

            $dirs = glob($tempBase . '/update_*', GLOB_ONLYDIR);
            if ($dirs === false) {
                return;
            }

            foreach ($dirs as $dir) {
                $mtime = @filemtime($dir);
                if ($mtime === false) {
                    continue;
                }

                // Delete directories older than 24 hours
                if (($now - $mtime) > $maxAge) {
                    try {
                        $this->recursiveDelete($dir);
                    } catch (Exception $e) {
                        // Ignore errors during cleanup
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore all cleanup errors to not break the constructor
        }
    }

    /**
     * Comprehensive diagnostic function for update failures
     * 
     * @param Exception|null $exception Optional exception that triggered the diagnostic
     * @param array $context Additional context information about the failure
     * @return array Detailed diagnostic report for support analysis
     */
    public function runUpdateDiagnostics(?Exception $exception = null, array $context = []): array
    {
        $this->diagnosticLog = [];
        $appRoot = dirname(dirname(__DIR__));

        $diagnostics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => $this->diagnoseSystemEnvironment(),
            'permissions' => $this->diagnoseFilePermissions($appRoot),
            'disk_space' => $this->diagnoseDiskSpace($appRoot),
            'network' => $this->diagnoseNetworkConnectivity(),
            'dependencies' => $this->diagnoseDependencies(),
            'update_history' => $this->diagnoseUpdateHistory(),
            'configuration' => $this->diagnoseConfiguration($appRoot),
            'error_analysis' => $this->analyzeError($exception, $context),
            'severity' => 'info'
        ];

        // Calculate overall severity
        $diagnostics['severity'] = $this->calculateSeverity($diagnostics);

        // Save diagnostic report to file
        $this->saveDiagnosticReport($diagnostics);

        return $diagnostics;
    }

    /**
     * Diagnose system environment (PHP version, extensions, settings)
     */
    private function diagnoseSystemEnvironment(): array
    {
        $requiredExtensions = ['curl', 'zip', 'json', 'mbstring', 'openssl'];
        $recommendedExtensions = ['fileinfo', 'dom', 'xml'];

        $loadedExtensions = get_loaded_extensions();
        $missingRequired = array_diff($requiredExtensions, $loadedExtensions);
        $missingRecommended = array_diff($recommendedExtensions, $loadedExtensions);

        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $memoryAdequate = $memoryLimitBytes >= (128 * 1024 * 1024); // 128 MB minimum

        $maxExecutionTime = ini_get('max_execution_time');
        $timeoutAdequate = ($maxExecutionTime == 0 || $maxExecutionTime >= 300); // 5 minutes minimum

        return [
            'php_version' => PHP_VERSION,
            'php_version_adequate' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'os' => PHP_OS,
            'sapi' => php_sapi_name(),
            'memory_limit' => $memoryLimit,
            'memory_adequate' => $memoryAdequate,
            'max_execution_time' => $maxExecutionTime,
            'timeout_adequate' => $timeoutAdequate,
            'loaded_extensions' => $loadedExtensions,
            'missing_required_extensions' => $missingRequired,
            'missing_recommended_extensions' => $missingRecommended,
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'disable_functions' => ini_get('disable_functions'),
            'open_basedir' => ini_get('open_basedir'),
            'status' => empty($missingRequired) && $memoryAdequate && $timeoutAdequate ? 'ok' : 'warning'
        ];
    }

    /**
     * Diagnose file system permissions
     */
    private function diagnoseFilePermissions(string $appRoot): array
    {
        $criticalPaths = [
            'root' => $appRoot,
            'system' => $appRoot . '/system',
            'system/updates' => $appRoot . '/system/updates',
            'storage' => $appRoot . '/storage',
            'storage/temp' => $appRoot . '/storage/temp',
            'vendor' => $appRoot . '/vendor',
            'src' => $appRoot . '/src',
            'assets' => $appRoot . '/assets',
            'composer.json' => $appRoot . '/composer.json',
            'composer.lock' => $appRoot . '/composer.lock'
        ];

        $permissions = [];
        $issues = [];

        foreach ($criticalPaths as $name => $path) {
            $exists = file_exists($path);
            $readable = $exists ? is_readable($path) : false;
            $writable = $exists ? is_writable($path) : false;
            $isDir = $exists ? is_dir($path) : false;
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : null;

            $permissions[$name] = [
                'path' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'writable' => $writable,
                'is_directory' => $isDir,
                'permissions' => $perms,
                'status' => 'ok'
            ];

            if (!$exists) {
                $permissions[$name]['status'] = 'error';
                $issues[] = "Pfad existiert nicht: {$name}";
            } elseif (!$readable) {
                $permissions[$name]['status'] = 'error';
                $issues[] = "Pfad nicht lesbar: {$name}";
            } elseif (!$writable && !in_array($name, ['root'])) { // root might be read-only
                $permissions[$name]['status'] = 'warning';
                $issues[] = "Pfad nicht beschreibbar: {$name}";
            }
        }

        return [
            'permissions' => $permissions,
            'issues' => $issues,
            'status' => empty($issues) ? 'ok' : (count(array_filter($issues, fn($i) => strpos($i, 'nicht lesbar') !== false || strpos($i, 'existiert nicht') !== false)) > 0 ? 'error' : 'warning')
        ];
    }

    /**
     * Diagnose disk space availability
     */
    private function diagnoseDiskSpace(string $appRoot): array
    {
        $freeSpace = @disk_free_space($appRoot);
        $totalSpace = @disk_total_space($appRoot);

        $tempDir = $appRoot . '/storage/temp';
        $tempFreeSpace = file_exists($tempDir) ? @disk_free_space($tempDir) : $freeSpace;

        $requiredSpace = 200 * 1024 * 1024; // 200 MB
        $recommendedSpace = 500 * 1024 * 1024; // 500 MB

        $adequate = $freeSpace !== false && $freeSpace >= $requiredSpace;
        $comfortable = $freeSpace !== false && $freeSpace >= $recommendedSpace;

        // Calculate size of key directories
        $storageSize = $this->getDirectorySize($appRoot . '/storage');
        $backupSize = $this->getDirectorySize($appRoot . '/system/updates');

        // Count temp update directories
        $tempUpdateDirs = glob($appRoot . '/storage/temp/update_*');
        $tempUpdateCount = is_array($tempUpdateDirs) ? count($tempUpdateDirs) : 0;

        return [
            'free_space' => $freeSpace,
            'free_space_mb' => $freeSpace !== false ? round($freeSpace / 1024 / 1024, 2) : null,
            'total_space' => $totalSpace,
            'total_space_mb' => $totalSpace !== false ? round($totalSpace / 1024 / 1024, 2) : null,
            'usage_percent' => ($freeSpace !== false && $totalSpace !== false) ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2) : null,
            'temp_free_space_mb' => $tempFreeSpace !== false ? round($tempFreeSpace / 1024 / 1024, 2) : null,
            'storage_size_mb' => round($storageSize / 1024 / 1024, 2),
            'backup_size_mb' => round($backupSize / 1024 / 1024, 2),
            'temp_update_dirs_count' => $tempUpdateCount,
            'required_space_mb' => round($requiredSpace / 1024 / 1024, 2),
            'adequate' => $adequate,
            'comfortable' => $comfortable,
            'status' => $adequate ? ($comfortable ? 'ok' : 'warning') : 'error'
        ];
    }

    /**
     * Diagnose network connectivity to GitHub
     */
    private function diagnoseNetworkConnectivity(): array
    {
        $tests = [];
        $overallStatus = 'ok';

        // Test 1: GitHub API connectivity
        $apiTest = $this->testGitHubAPI();
        $tests['github_api'] = $apiTest;
        if ($apiTest['status'] !== 'ok') {
            $overallStatus = 'error';
        }

        // Test 2: SSL/TLS configuration
        $sslTest = $this->testSSLConfiguration();
        $tests['ssl_config'] = $sslTest;
        if ($sslTest['status'] !== 'ok' && $overallStatus === 'ok') {
            $overallStatus = 'warning';
        }

        // Test 3: DNS resolution
        $dnsTest = $this->testDNSResolution('api.github.com');
        $tests['dns_resolution'] = $dnsTest;
        if ($dnsTest['status'] !== 'ok' && $overallStatus === 'ok') {
            $overallStatus = 'warning';
        }

        // Test 4: Proxy detection
        $proxyTest = $this->detectProxyConfiguration();
        $tests['proxy'] = $proxyTest;

        return [
            'tests' => $tests,
            'status' => $overallStatus
        ];
    }

    /**
     * Test GitHub API connectivity
     */
    private function testGitHubAPI(): array
    {
        $startTime = microtime(true);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: intraRP-Updater-Diagnostic',
                    'Accept: application/vnd.github+json'
                ],
                'timeout' => 10
            ]
        ]);

        $testUrl = $this->githubApiUrl;
        $response = @file_get_contents($testUrl, false, $context);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($response === false) {
            $error = error_get_last();
            return [
                'accessible' => false,
                'error' => $error['message'] ?? 'Unbekannter Fehler',
                'response_time_ms' => $responseTime,
                'status' => 'error'
            ];
        }

        $data = json_decode($response, true);
        $rateLimitRemaining = null;
        $rateLimitReset = null;

        // Try to get rate limit info from headers if available
        if (is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'X-RateLimit-Remaining:') === 0) {
                    $rateLimitRemaining = (int)trim(substr($header, 23));
                }
                if (stripos($header, 'X-RateLimit-Reset:') === 0) {
                    $rateLimitReset = (int)trim(substr($header, 19));
                }
            }
        }

        return [
            'accessible' => true,
            'response_time_ms' => $responseTime,
            'rate_limit_remaining' => $rateLimitRemaining,
            'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
            'status' => $responseTime < 3000 ? 'ok' : 'warning'
        ];
    }

    /**
     * Test SSL/TLS configuration
     */
    private function testSSLConfiguration(): array
    {
        $hasOpenSSL = extension_loaded('openssl');
        $hasCurl = extension_loaded('curl');

        if (!$hasOpenSSL && !$hasCurl) {
            return [
                'openssl_enabled' => false,
                'curl_enabled' => false,
                'status' => 'error',
                'message' => 'Weder OpenSSL noch cURL verfügbar'
            ];
        }

        $caInfo = ini_get('openssl.cafile');
        $caPath = ini_get('openssl.capath');
        $curlCaInfo = ini_get('curl.cainfo');

        return [
            'openssl_enabled' => $hasOpenSSL,
            'openssl_version' => $hasOpenSSL ? OPENSSL_VERSION_TEXT : null,
            'curl_enabled' => $hasCurl,
            'curl_version' => $hasCurl ? curl_version()['version'] : null,
            'ca_file' => $caInfo ?: $curlCaInfo ?: null,
            'ca_path' => $caPath,
            'status' => ($hasOpenSSL || $hasCurl) ? 'ok' : 'error'
        ];
    }

    /**
     * Test DNS resolution
     */
    private function testDNSResolution(string $hostname): array
    {
        $startTime = microtime(true);
        $ip = @gethostbyname($hostname);
        $resolveTime = round((microtime(true) - $startTime) * 1000, 2);

        $resolved = ($ip !== $hostname);

        return [
            'hostname' => $hostname,
            'resolved' => $resolved,
            'ip_address' => $resolved ? $ip : null,
            'resolve_time_ms' => $resolveTime,
            'status' => $resolved ? 'ok' : 'error'
        ];
    }

    /**
     * Detect proxy configuration
     */
    private function detectProxyConfiguration(): array
    {
        $httpProxy = getenv('HTTP_PROXY') ?: getenv('http_proxy');
        $httpsProxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
        $noProxy = getenv('NO_PROXY') ?: getenv('no_proxy');

        return [
            'http_proxy' => $httpProxy ?: null,
            'https_proxy' => $httpsProxy ?: null,
            'no_proxy' => $noProxy ?: null,
            'proxy_detected' => !empty($httpProxy) || !empty($httpsProxy),
            'status' => 'info'
        ];
    }

    /**
     * Diagnose dependencies (Composer, PHP extensions)
     */
    private function diagnoseDependencies(): array
    {
        $composerPath = $this->findComposerExecutable();
        $composerAvailable = !empty($composerPath);
        $composerVersion = null;

        if ($composerAvailable) {
            $output = [];
            $returnCode = 0;
            @exec(escapeshellarg($composerPath) . ' --version 2>&1', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                if (preg_match('/Composer version ([0-9.]+)/', implode(' ', $output), $matches)) {
                    $composerVersion = $matches[1];
                }
            }
        }

        $appRoot = dirname(dirname(__DIR__));
        $composerJsonExists = file_exists($appRoot . '/composer.json');
        $composerLockExists = file_exists($appRoot . '/composer.lock');
        $vendorExists = is_dir($appRoot . '/vendor');
        $autoloadExists = file_exists($appRoot . '/vendor/autoload.php');

        $composerLockAge = null;
        if ($composerLockExists) {
            $lockMtime = filemtime($appRoot . '/composer.lock');
            $composerLockAge = floor((time() - $lockMtime) / 86400); // days
        }

        return [
            'composer_available' => $composerAvailable,
            'composer_path' => $composerPath,
            'composer_version' => $composerVersion,
            'composer_json_exists' => $composerJsonExists,
            'composer_lock_exists' => $composerLockExists,
            'composer_lock_age_days' => $composerLockAge,
            'vendor_directory_exists' => $vendorExists,
            'autoload_exists' => $autoloadExists,
            'note' => 'Composer ist optional - kann bei Hosting-Umgebungen separat ausgeführt werden',
            'status' => ($vendorExists && $autoloadExists) ? 'ok' : 'info'
        ];
    }

    /**
     * Diagnose update history
     */
    private function diagnoseUpdateHistory(): array
    {
        $appRoot = dirname(dirname(__DIR__));
        $updatesDir = $appRoot . '/system/updates';

        $backups = [];
        if (is_dir($updatesDir)) {
            $backupDirs = glob($updatesDir . '/backup_*', GLOB_ONLYDIR);
            foreach ($backupDirs as $dir) {
                $backups[] = [
                    'name' => basename($dir),
                    'path' => $dir,
                    'created' => date('Y-m-d H:i:s', filemtime($dir)),
                    'size_mb' => $this->getDirectorySize($dir) / 1024 / 1024
                ];
            }
        }

        // Check for recent temp update directories (potential failed updates)
        $tempBase = $appRoot . '/storage/temp';
        $tempUpdateDirs = [];
        if (is_dir($tempBase)) {
            $dirs = glob($tempBase . '/update_*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $tempUpdateDirs[] = [
                    'name' => basename($dir),
                    'created' => date('Y-m-d H:i:s', filemtime($dir)),
                    'age_hours' => floor((time() - filemtime($dir)) / 3600)
                ];
            }
        }

        // Read diagnostic log if exists
        $previousDiagnostics = [];
        if (file_exists($this->diagnosticFile)) {
            $logContent = @file_get_contents($this->diagnosticFile);
            if ($logContent) {
                $lines = explode("\n", $logContent);
                $previousDiagnostics = array_slice(array_filter($lines), -10); // Last 10 entries
            }
        }

        return [
            'current_version' => $this->currentVersion,
            'version_age_days' => $this->getVersionAge(),
            'backups' => $backups,
            'backup_count' => count($backups),
            'temp_update_dirs' => $tempUpdateDirs,
            'failed_update_indicators' => count($tempUpdateDirs),
            'previous_diagnostics' => $previousDiagnostics,
            'status' => count($tempUpdateDirs) > 3 ? 'warning' : 'ok'
        ];
    }

    /**
     * Diagnose system configuration
     */
    private function diagnoseConfiguration(string $appRoot): array
    {
        $envExists = file_exists($appRoot . '/.env');
        $htaccessExists = file_exists($appRoot . '/.htaccess');
        $gitExists = is_dir($appRoot . '/.git');

        return [
            'env_file_exists' => $envExists,
            'htaccess_exists' => $htaccessExists,
            'git_repository' => $gitExists,
            'is_plesk' => $this->detectPlesk(),
            'is_cpanel' => $this->detectCPanel(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'status' => 'ok'
        ];
    }

    /**
     * Analyze specific error
     */
    private function analyzeError(?Exception $exception, array $context): array
    {
        if (!$exception) {
            return [
                'has_error' => false,
                'message' => null,
                'error_type' => null,
                'likely_causes' => [],
                'solutions' => []
            ];
        }

        $message = $exception->getMessage();
        $errorType = $this->classifyError($message);
        $likelyCauses = $this->identifyLikelyCauses($errorType, $message, $context);
        $solutions = $this->provideSolutions($errorType, $message, $context);

        return [
            'has_error' => true,
            'message' => $message,
            'exception_class' => get_class($exception),
            'error_type' => $errorType,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'likely_causes' => $likelyCauses,
            'solutions' => $solutions
        ];
    }

    /**
     * Classify error type based on message
     */
    private function classifyError(string $message): string
    {
        $patterns = [
            'network' => '/(?:failed to open stream|connection|timeout|could not resolve host|SSL|certificate)/i',
            'permissions' => '/(?:permission denied|not writable|not readable|failed to open|mkdir|rmdir|unlink)/i',
            'disk_space' => '/(?:disk|space|storage|no space left|bytes written|possibly out of free disk space|file_put_contents.*bytes)/i',
            'zip' => '/(?:zip|extract|archive|corrupt)/i',
            'composer' => '/(?:composer|dependency|autoload|vendor)/i',
            'php_version' => '/(?:version|compatibility|deprecated)/i',
            'memory' => '/(?:memory|allocation|exhausted)/i',
            'download' => '/(?:download|fetch|retrieve|zipball)/i',
            'backup' => '/(?:backup|restore|rollback)/i',
            'github_api' => '/(?:github|api|rate limit|repository)/i'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $message)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Identify likely causes based on error type
     */
    private function identifyLikelyCauses(string $errorType, string $message, array $context): array
    {
        $causes = [
            'network' => [
                'Keine Internetverbindung oder instabile Verbindung',
                'Firewall blockiert Zugriff auf GitHub',
                'Proxy-Server nicht korrekt konfiguriert',
                'GitHub API temporär nicht erreichbar',
                'SSL-Zertifikat-Problem'
            ],
            'permissions' => [
                'Datei-/Verzeichnisrechte zu restriktiv (nicht 755/644)',
                'Webserver läuft unter anderem Benutzer als Dateien',
                'SELinux oder ähnliche Sicherheitsmechanismen aktiv',
                'Verzeichnis ist schreibgeschützt',
                'Parent-Verzeichnis existiert nicht'
            ],
            'disk_space' => [
                'Zu wenig freier Speicherplatz auf dem Server',
                'Disk-Quota des Hosting-Pakets erreicht',
                'Temporäres Verzeichnis (/tmp oder storage/temp) voll',
                'Inode-Limit erreicht (zu viele Dateien)',
                'Update-Datei zu groß für verfügbaren Speicher',
                'Dateisystem nur noch im Read-Only-Modus'
            ],
            'zip' => [
                'ZIP-Datei wurde nicht vollständig heruntergeladen',
                'ZIP-Datei ist beschädigt',
                'PHP ZipArchive-Extension fehlt',
                'ZIP-Datei zu groß für verfügbaren Speicher'
            ],
            'composer' => [
                'Composer nicht installiert',
                'Composer-Abhängigkeiten fehlen oder veraltet',
                'composer.json oder composer.lock beschädigt',
                'Inkompatible PHP-Version für Abhängigkeiten'
            ],
            'memory' => [
                'PHP memory_limit zu niedrig (< 128M empfohlen)',
                'Update-Archiv zu groß',
                'Zu viele Dateien gleichzeitig im Speicher'
            ],
            'github_api' => [
                'GitHub API Rate-Limit erreicht (60 Anfragen/Stunde ohne Token)',
                'Ungültiger oder abgelaufener GitHub-Token',
                'Repository nicht zugänglich',
                'Release nicht gefunden'
            ],
            'download' => [
                'Download-URL ungültig',
                'GitHub-Server überlastet',
                'Netzwerk-Timeout während des Downloads',
                'Datei zu groß für PHP-Limits'
            ]
        ];

        return $causes[$errorType] ?? ['Unbekannte Ursache - bitte Fehlermeldung analysieren'];
    }

    /**
     * Provide solutions based on error type
     */
    private function provideSolutions(string $errorType, string $message, array $context): array
    {
        $solutions = [
            'network' => [
                'Internetverbindung prüfen',
                'Firewall-Regeln für ausgehende HTTPS-Verbindungen zu github.com erlauben',
                'Proxy-Einstellungen in PHP/Server-Konfiguration prüfen',
                'SSL-Zertifikate aktualisieren (CA-Bundle)',
                'Später erneut versuchen, falls GitHub temporäre Probleme hat'
            ],
            'permissions' => [
                'Verzeichnisrechte auf 755 setzen: chmod -R 755 /pfad/zum/verzeichnis',
                'Dateirechte auf 644 setzen: chmod -R 644 /pfad/zu/dateien',
                'Eigentümer anpassen: chown -R www-data:www-data /pfad',
                'SELinux-Kontext anpassen falls nötig',
                'Webserver-Prozess-Benutzer identifizieren (ps aux | grep apache/nginx)'
            ],
            'disk_space' => [
                'Speicherplatz freigeben: storage/temp/* und system/updates/backup_* löschen',
                'Alte Dateien in storage/cache/* und storage/documents/* aufräumen',
                'Bei Plesk/cPanel: Disk-Quota im Hosting-Panel prüfen und erhöhen',
                'Backup-Dateien auf lokalen Computer herunterladen und vom Server löschen',
                '/tmp-Verzeichnis leeren (ggf. über SSH/FTP)',
                'Logs bereinigen: Alte Dateien in *.log umbenennen oder löschen',
                'Bei Shared Hosting: Hosting-Paket upgraden für mehr Speicherplatz',
                'Composer vendor-Verzeichnis temporär löschen (wird bei Update neu erstellt)'
            ],
            'zip' => [
                'php-zip Extension installieren: apt-get install php-zip (Debian/Ubuntu)',
                'Download erneut versuchen',
                'Netzwerk-Stabilität prüfen',
                'PHP memory_limit erhöhen'
            ],
            'composer' => [
                'Composer installieren: https://getcomposer.org/download/',
                'composer install manuell ausführen',
                'composer update zur Aktualisierung der Abhängigkeiten',
                'vendor-Verzeichnis löschen und neu installieren',
                'PHP-Version prüfen und ggf. aktualisieren'
            ],
            'memory' => [
                'PHP memory_limit in php.ini erhöhen (empfohlen: 256M oder höher)',
                'Apache/Nginx neu starten nach php.ini-Änderung',
                'Unnötige PHP-Module deaktivieren',
                'Update in Teilschritten durchführen (falls möglich)'
            ],
            'github_api' => [
                'Eine Stunde warten (Rate-Limit-Reset)',
                'GitHub Personal Access Token generieren und verwenden',
                'Weniger häufig nach Updates suchen',
                'Cache leeren und erneut versuchen'
            ],
            'download' => [
                'Download erneut versuchen',
                'max_execution_time in php.ini erhöhen',
                'Zu einer Zeit mit besserer Netzwerk-Performance versuchen',
                'Manuellen Download und Upload erwägen'
            ],
            'backup' => [
                'Alte Backups manuell wiederherstellen aus system/updates/backup_*',
                'Speicherplatz für Backups sicherstellen',
                'Backup-Verzeichnis auf Schreibrechte prüfen'
            ],
            'unknown' => [
                'Fehlerlog prüfen (PHP error log, Webserver log)',
                'Mit Debug-Informationen Support kontaktieren',
                'Manuelle Installation erwägen',
                'PHP-Version und Extensions prüfen'
            ]
        ];

        return $solutions[$errorType] ?? $solutions['unknown'];
    }

    /**
     * Calculate overall severity based on diagnostic findings
     */
    private function calculateSeverity(array $diagnostics): string
    {
        $errorCount = 0;
        $warningCount = 0;

        foreach ($diagnostics as $key => $section) {
            if (is_array($section) && isset($section['status'])) {
                if ($section['status'] === 'error') {
                    $errorCount++;
                } elseif ($section['status'] === 'warning') {
                    $warningCount++;
                }
            }
        }

        if ($errorCount > 0) {
            return 'error';
        } elseif ($warningCount > 1) {
            return 'warning';
        } elseif ($warningCount > 0) {
            return 'info';
        }

        return 'ok';
    }

    /**
     * Save diagnostic report to file
     */
    private function saveDiagnosticReport(array $diagnostics): void
    {
        try {
            $dir = dirname($this->diagnosticFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $timestamp = $diagnostics['timestamp'];
            $severity = $diagnostics['severity'];
            $errorMsg = $diagnostics['error_analysis']['message'] ?? 'Manuelle Diagnose';

            $logEntry = sprintf(
                "[%s] [%s] %s\n",
                $timestamp,
                strtoupper($severity),
                substr($errorMsg, 0, 200)
            );

            // Append to log file
            file_put_contents($this->diagnosticFile, $logEntry, FILE_APPEND);

            // Save full diagnostic as JSON
            $jsonFile = str_replace('.log', '_' . date('Ymd_His') . '.json', $this->diagnosticFile);
            file_put_contents($jsonFile, json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Keep only last 10 JSON files
            $jsonFiles = glob(dirname($this->diagnosticFile) . '/diagnostic_*.json');
            if (count($jsonFiles) > 10) {
                usort($jsonFiles, function ($a, $b) {
                    return filemtime($a) <=> filemtime($b);
                });
                $filesToDelete = array_slice($jsonFiles, 0, count($jsonFiles) - 10);
                foreach ($filesToDelete as $file) {
                    @unlink($file);
                }
            }
        } catch (Exception $e) {
            // Silently fail - don't break the diagnostic function
        }
    }

    /**
     * Get the latest diagnostic report
     */
    public function getLatestDiagnosticReport(): ?array
    {
        $jsonFiles = glob(dirname($this->diagnosticFile) . '/diagnostic_*.json');
        if (empty($jsonFiles)) {
            return null;
        }

        usort($jsonFiles, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $latestFile = $jsonFiles[0];
        $content = file_get_contents($latestFile);
        return json_decode($content, true);
    }

    /**
     * Helper: Convert PHP size notation to bytes
     */
    private function convertToBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int)$size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Helper: Get directory size in bytes
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            // Return 0 on error
        }
        return $size;
    }

    /**
     * Helper: Detect Plesk environment
     */
    private function detectPlesk(): bool
    {
        return file_exists('/usr/local/psa/version') ||
            file_exists('/opt/psa/version') ||
            (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'plesk') !== false);
    }

    /**
     * Helper: Detect cPanel environment
     */
    private function detectCPanel(): bool
    {
        return file_exists('/usr/local/cpanel/version') ||
            (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'cpanel') !== false);
    }

    /**
     * Format diagnostic summary for user display (plain text)
     */
    private function formatDiagnosticSummary(array $diagnostics): string
    {
        $summary = [];
        $summary[] = "=== Update-Diagnose ===\n";
        $summary[] = "Schweregrad: " . strtoupper($diagnostics['severity']);
        $summary[] = "Zeitpunkt: " . $diagnostics['timestamp'];

        if ($diagnostics['error_analysis']['has_error']) {
            $summary[] = "\nFehlertyp: " . $diagnostics['error_analysis']['error_type'];
            $summary[] = "Nachricht: " . substr($diagnostics['error_analysis']['message'], 0, 200);
        }

        $summary[] = "\n=== System-Status ===";
        $summary[] = "PHP: " . $diagnostics['system_info']['php_version'] . " (" . $diagnostics['system_info']['status'] . ")";
        $summary[] = "Speicher: " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB frei (" . $diagnostics['disk_space']['status'] . ")";
        $summary[] = "Berechtigungen: " . $diagnostics['permissions']['status'];
        $summary[] = "Netzwerk: " . $diagnostics['network']['status'];
        $summary[] = "Vendor: " . ($diagnostics['dependencies']['vendor_directory_exists'] ? 'vorhanden' : 'fehlt');

        $summary[] = "\n=== Problembereiche ===";
        $issues = [];

        if ($diagnostics['system_info']['status'] !== 'ok') {
            $issues[] = "• System-Umgebung: " . $diagnostics['system_info']['status'];
            if (!empty($diagnostics['system_info']['missing_required_extensions'])) {
                $issues[] = "  - Fehlende Extensions: " . implode(', ', $diagnostics['system_info']['missing_required_extensions']);
            }
        }
        if ($diagnostics['permissions']['status'] !== 'ok') {
            $issues[] = "• Berechtigungen: " . $diagnostics['permissions']['status'];
            if (!empty($diagnostics['permissions']['issues'])) {
                foreach (array_slice($diagnostics['permissions']['issues'], 0, 3) as $issue) {
                    $issues[] = "  - " . $issue;
                }
            }
        }
        if ($diagnostics['disk_space']['status'] !== 'ok') {
            $issues[] = "• Speicherplatz: " . $diagnostics['disk_space']['status'];
        }
        if ($diagnostics['network']['status'] !== 'ok') {
            $issues[] = "• Netzwerk: " . $diagnostics['network']['status'];
        }

        if (empty($issues)) {
            $summary[] = "Keine kritischen Probleme erkannt.";
        } else {
            $summary = array_merge($summary, $issues);
        }

        $summary[] = "\nVollständige Diagnose wurde gespeichert.";
        $summary[] = "Bitte kontaktieren Sie den Support mit diesem Bericht.";

        return implode("\n", $summary);
    }

    /**
     * Format diagnostic summary as HTML for UI display
     * 
     * @param array $diagnostics Complete diagnostic data
     * @return string HTML-formatted diagnostic summary
     */
    public function formatDiagnosticHTML(array $diagnostics): string
    {
        $severityClass = match ($diagnostics['severity']) {
            'error' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            'ok' => 'success',
            default => 'secondary'
        };

        $severityIcon = match ($diagnostics['severity']) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'ok' => '✅',
            default => '•'
        };

        $html = [];
        $html[] = "<div class='diagnostic-report'>";
        $html[] = "  <div class='alert alert-{$severityClass}'>";
        $html[] = "    <h4>{$severityIcon} Update-Diagnose</h4>";
        $html[] = "    <div class='row mb-2'>";
        $html[] = "      <div class='col-6'><strong>Schweregrad:</strong> " . strtoupper($diagnostics['severity']) . "</div>";
        $html[] = "      <div class='col-6'><strong>Zeitpunkt:</strong> {$diagnostics['timestamp']}</div>";
        $html[] = "    </div>";

        if ($diagnostics['error_analysis']['has_error']) {
            $html[] = "    <hr>";
            $html[] = "    <div class='error-details'>";
            $html[] = "      <strong>Fehlertyp:</strong> <code>{$diagnostics['error_analysis']['error_type']}</code><br>";
            $html[] = "      <strong>Nachricht:</strong> " . htmlspecialchars(substr($diagnostics['error_analysis']['message'], 0, 300)) . "";
            $html[] = "    </div>";
        }

        $html[] = "  </div>";

        // System Status
        $html[] = "  <div class='card mb-3'>";
        $html[] = "    <div class='card-header'><strong>System-Status</strong></div>";
        $html[] = "    <div class='card-body'>";
        $html[] = "      <div class='row'>";
        $html[] = "        <div class='col-md-4'>";
        $html[] = "          <strong>PHP:</strong> {$diagnostics['system_info']['php_version']}<br>";
        $html[] = "          <small class='text-" . $this->getStatusClass($diagnostics['system_info']['status']) . "'>{$diagnostics['system_info']['status']}</small>";
        $html[] = "        </div>";
        $html[] = "        <div class='col-md-4'>";
        $html[] = "          <strong>Speicher:</strong> " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB<br>";
        $html[] = "          <small class='text-" . $this->getStatusClass($diagnostics['disk_space']['status']) . "'>{$diagnostics['disk_space']['status']}</small>";
        $html[] = "        </div>";
        $html[] = "        <div class='col-md-4'>";
        $html[] = "          <strong>Netzwerk:</strong> GitHub API<br>";
        $html[] = "          <small class='text-" . $this->getStatusClass($diagnostics['network']['status']) . "'>{$diagnostics['network']['status']}</small>";
        $html[] = "        </div>";
        $html[] = "      </div>";
        $html[] = "      <div class='row mt-2'>";
        $html[] = "        <div class='col-md-4'>";
        $html[] = "          <strong>Berechtigungen:</strong><br>";
        $html[] = "          <small class='text-" . $this->getStatusClass($diagnostics['permissions']['status']) . "'>{$diagnostics['permissions']['status']}</small>";
        $html[] = "        </div>";
        $html[] = "        <div class='col-md-4'>";
        $html[] = "          <strong>Vendor:</strong><br>";
        $html[] = "          <small>" . ($diagnostics['dependencies']['vendor_directory_exists'] ? '✓ vorhanden' : '✗ fehlt') . "</small>";
        $html[] = "        </div>";
        $html[] = "      </div>";
        $html[] = "    </div>";
        $html[] = "  </div>";

        // Problems
        $problems = [];
        if ($diagnostics['system_info']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'System-Umgebung',
                'status' => $diagnostics['system_info']['status'],
                'details' => !empty($diagnostics['system_info']['missing_required_extensions'])
                    ? 'Fehlende Extensions: ' . implode(', ', $diagnostics['system_info']['missing_required_extensions'])
                    : null
            ];
        }
        if ($diagnostics['permissions']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'Berechtigungen',
                'status' => $diagnostics['permissions']['status'],
                'details' => !empty($diagnostics['permissions']['issues'])
                    ? implode('<br>', array_slice($diagnostics['permissions']['issues'], 0, 3))
                    : null
            ];
        }
        if ($diagnostics['disk_space']['status'] !== 'ok') {
            $details = 'Nur ' . ($diagnostics['disk_space']['free_space_mb'] ?? 0) . ' MB frei';
            if (isset($diagnostics['disk_space']['storage_size_mb'])) {
                $details .= '<br>storage: ' . $diagnostics['disk_space']['storage_size_mb'] . ' MB';
            }
            if (isset($diagnostics['disk_space']['backup_size_mb'])) {
                $details .= ', backups: ' . $diagnostics['disk_space']['backup_size_mb'] . ' MB';
            }
            if (isset($diagnostics['disk_space']['temp_update_dirs_count']) && $diagnostics['disk_space']['temp_update_dirs_count'] > 0) {
                $details .= '<br>' . $diagnostics['disk_space']['temp_update_dirs_count'] . ' fehlgeschlagene Update-Verzeichnisse';
            }

            $problems[] = [
                'title' => 'Speicherplatz',
                'status' => $diagnostics['disk_space']['status'],
                'details' => $details
            ];
        }
        if ($diagnostics['network']['status'] !== 'ok') {
            $problems[] = [
                'title' => 'Netzwerk',
                'status' => $diagnostics['network']['status'],
                'details' => 'GitHub API nicht erreichbar'
            ];
        }

        if (!empty($problems)) {
            $html[] = "  <div class='card mb-3'>";
            $html[] = "    <div class='card-header bg-warning'><strong>⚠️ Problembereiche</strong></div>";
            $html[] = "    <div class='card-body'>";
            $html[] = "      <ul class='mb-0'>";
            foreach ($problems as $problem) {
                $html[] = "        <li>";
                $html[] = "          <strong>{$problem['title']}:</strong> ";
                $html[] = "          <span class='badge badge-" . $this->getStatusClass($problem['status']) . "'>{$problem['status']}</span>";
                if ($problem['details']) {
                    $html[] = "          <br><small class='text-muted'>{$problem['details']}</small>";
                }
                $html[] = "        </li>";
            }
            $html[] = "      </ul>";
            $html[] = "    </div>";
            $html[] = "  </div>";
        } else {
            $html[] = "  <div class='alert alert-success'>";
            $html[] = "    ✓ Keine kritischen Probleme erkannt.";
            $html[] = "  </div>";
        }

        // Support Info
        $html[] = "  <div class='card'>";
        $html[] = "    <div class='card-body text-center'>";
        $html[] = "      <p class='mb-2'><strong>Diagnose wurde gespeichert.</strong></p>";
        $html[] = "      <p class='mb-2'>Bitte kontaktieren Sie den Support mit diesem Bericht.</p>";
        $html[] = "      <button class='btn btn-primary btn-sm' onclick='copyDiagnosticReport()'>📋 In Zwischenablage kopieren</button>";
        $html[] = "      <button class='btn btn-secondary btn-sm' onclick='downloadDiagnosticReport()'>💾 Als Datei herunterladen</button>";
        $html[] = "    </div>";
        $html[] = "  </div>";

        $html[] = "</div>";

        return implode("\n", $html);
    }

    /**
     * Generate support export text (easy to copy/paste)
     * 
     * @param array $diagnostics Complete diagnostic data
     * @return string Formatted text for support ticket
     */
    public function formatDiagnosticForSupport(array $diagnostics): string
    {
        $lines = [];
        $lines[] = "========================================";
        $lines[] = "intraRP System-Diagnose";
        $lines[] = "========================================";
        $lines[] = "";
        $lines[] = "Zeitpunkt: " . $diagnostics['timestamp'];
        $lines[] = "Schweregrad: " . strtoupper($diagnostics['severity']);
        $lines[] = "Version: " . ($diagnostics['update_history']['current_version']['version'] ?? 'unbekannt');
        $lines[] = "";

        if ($diagnostics['error_analysis']['has_error']) {
            $lines[] = "FEHLER-DETAILS:";
            $lines[] = "---------------";
            $lines[] = "Typ: " . $diagnostics['error_analysis']['error_type'];
            $lines[] = "Nachricht: " . $diagnostics['error_analysis']['message'];
            $lines[] = "";
        }

        $lines[] = "SYSTEM-INFORMATION:";
        $lines[] = "-------------------";
        $lines[] = "PHP Version: " . $diagnostics['system_info']['php_version'];
        $lines[] = "Betriebssystem: " . $diagnostics['system_info']['os'];
        $lines[] = "SAPI: " . $diagnostics['system_info']['sapi'];
        $lines[] = "Memory Limit: " . $diagnostics['system_info']['memory_limit'];
        $lines[] = "Max Execution Time: " . $diagnostics['system_info']['max_execution_time'] . "s";

        if (!empty($diagnostics['system_info']['missing_required_extensions'])) {
            $lines[] = "Fehlende Extensions: " . implode(', ', $diagnostics['system_info']['missing_required_extensions']);
        }
        $lines[] = "";

        $lines[] = "SPEICHER:";
        $lines[] = "---------";
        $lines[] = "Frei: " . ($diagnostics['disk_space']['free_space_mb'] ?? 'unbekannt') . " MB";
        $lines[] = "Gesamt: " . ($diagnostics['disk_space']['total_space_mb'] ?? 'unbekannt') . " MB";
        $lines[] = "Auslastung: " . ($diagnostics['disk_space']['usage_percent'] ?? 'unbekannt') . "%";
        if (isset($diagnostics['disk_space']['storage_size_mb'])) {
            $lines[] = "Storage-Verzeichnis: " . $diagnostics['disk_space']['storage_size_mb'] . " MB";
        }
        if (isset($diagnostics['disk_space']['backup_size_mb'])) {
            $lines[] = "Backup-Verzeichnis: " . $diagnostics['disk_space']['backup_size_mb'] . " MB";
        }
        if (isset($diagnostics['disk_space']['temp_update_dirs_count']) && $diagnostics['disk_space']['temp_update_dirs_count'] > 0) {
            $lines[] = "Fehlgeschlagene Update-Verzeichnisse: " . $diagnostics['disk_space']['temp_update_dirs_count'];
        }
        $lines[] = "Status: " . $diagnostics['disk_space']['status'];
        $lines[] = "";

        $lines[] = "BERECHTIGUNGEN:";
        $lines[] = "---------------";
        $lines[] = "Status: " . $diagnostics['permissions']['status'];
        if (!empty($diagnostics['permissions']['issues'])) {
            foreach ($diagnostics['permissions']['issues'] as $issue) {
                $lines[] = "  - " . $issue;
            }
        }
        $lines[] = "";

        $lines[] = "NETZWERK:";
        $lines[] = "---------";
        $lines[] = "Status: " . $diagnostics['network']['status'];
        $lines[] = "GitHub API: " . ($diagnostics['network']['tests']['github_api']['accessible'] ? 'erreichbar' : 'nicht erreichbar');
        if (isset($diagnostics['network']['tests']['github_api']['response_time_ms'])) {
            $lines[] = "Response Time: " . $diagnostics['network']['tests']['github_api']['response_time_ms'] . " ms";
        }
        $lines[] = "";

        $lines[] = "ABHÄNGIGKEITEN:";
        $lines[] = "---------------";
        $lines[] = "Composer: " . ($diagnostics['dependencies']['composer_available'] ? 'verfügbar' : 'nicht verfügbar');
        if ($diagnostics['dependencies']['composer_version']) {
            $lines[] = "  Version: " . $diagnostics['dependencies']['composer_version'];
        }
        $lines[] = "Vendor: " . ($diagnostics['dependencies']['vendor_directory_exists'] ? 'vorhanden' : 'fehlt');
        $lines[] = "Autoload: " . ($diagnostics['dependencies']['autoload_exists'] ? 'vorhanden' : 'fehlt');
        $lines[] = "";

        $lines[] = "KONFIGURATION:";
        $lines[] = "--------------";
        $lines[] = "Hosting: " . ($diagnostics['configuration']['is_plesk'] ? 'Plesk' : ($diagnostics['configuration']['is_cpanel'] ? 'cPanel' : 'Standard'));
        $lines[] = "Git Repository: " . ($diagnostics['configuration']['git_repository'] ? 'ja' : 'nein');
        $lines[] = "";

        $lines[] = "========================================";
        $lines[] = "Ende des Diagnose-Berichts";
        $lines[] = "========================================";

        return implode("\n", $lines);
    }

    /**
     * Helper: Get Bootstrap CSS class for status
     */
    private function getStatusClass(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
            default => 'secondary'
        };
    }
}
