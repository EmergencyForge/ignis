<?php

namespace App\Utils;

use App\Utils\Updater\BackupManager;
use App\Utils\Updater\ComposerRunner;
use App\Utils\Updater\DiagnosticFormatter;
use App\Utils\Updater\FileInstaller;
use App\Utils\Updater\GitHubReleaseSource;
use App\Utils\Updater\LegacyStorageMigration;
use App\Utils\Updater\ReleaseNotes;
use App\Utils\Updater\UpdateArchive;
use App\Utils\Updater\UpdateCheckCache;
use App\Utils\Updater\UpdateDiagnostics;
use App\Utils\Updater\VersionComparator;
use App\Utils\Updater\VersionStore;
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
    private string $appRoot;
    private GitHubReleaseSource $source;
    private VersionStore $versions;
    private UpdateCheckCache $cache;
    private UpdateDiagnostics $diagnostics;
    private string $diagnosticFile;

    /**
     * @param string|null $appRoot Installationsverzeichnis; Tests setzen ein Temp-Verzeichnis
     */
    public function __construct(?string $appRoot = null, ?GitHubReleaseSource $source = null)
    {
        $appRoot ??= dirname(__DIR__, 2);
        $this->appRoot = $appRoot;
        $this->source = $source ?? new GitHubReleaseSource();
        $this->versionFile = $appRoot . '/storage/version.json';
        $this->composerPendingFile = $appRoot . '/storage/composer_pending.json';
        $this->diagnosticFile = $appRoot . '/storage/logs/updater-diagnostic.log';
        LegacyStorageMigration::run($appRoot, [
            '/version.json'          => $this->versionFile,
            '/composer_pending.json' => $this->composerPendingFile,
            '/diagnostic.log'        => $this->diagnosticFile,
        ]);
        $this->versions = new VersionStore($this->versionFile);
        $this->cache = new UpdateCheckCache($appRoot . '/storage/cache/update-check.json');
        $this->diagnostics = new UpdateDiagnostics($appRoot, $this->diagnosticFile, $this->versions, $this->source);
        $this->cleanupOldTempDirectories();
    }

    /**
     * Get current version information
     */
    public function getCurrentVersion(): array
    {
        return $this->versions->current();
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

            $latestRelease = $this->source->latestRelease($includePreRelease);

            if (!$latestRelease) {
                return [
                    'available' => false,
                    'error' => true,
                    'message' => 'Konnte nicht auf GitHub-API zugreifen. Bitte prüfen Sie Ihre Internetverbindung oder versuchen Sie es später erneut (möglicherweise API-Ratenlimit erreicht).'
                ];
            }

            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->versions->current()['version'];

            $isNewer = VersionComparator::isNewer($latestVersion, $currentVersion);
            $isLatestPreRelease = $latestRelease['prerelease'] ?? false;

            $asset = GitHubReleaseSource::pickUpdateAsset($latestRelease);

            return [
                'available' => $isNewer,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_name' => $latestRelease['name'] ?? $latestVersion,
                'release_notes' => $latestRelease['body'] ?? 'Keine Release-Notizen verfügbar.',
                'published_at' => $latestRelease['published_at'] ?? null,
                'download_url' => $asset['download_url'],
                'download_url_fallback' => $latestRelease['zipball_url'] ?? null,
                'html_url' => $latestRelease['html_url'] ?? null,
                'is_prerelease' => $isLatestPreRelease,
                'has_release_asset' => $asset['has_release_asset'],
                'checksum_sha256' => $asset['checksum_sha256'],
                'download_size' => $asset['download_size'],
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
     * Check if PHP limits are sufficient for downloading and extracting updates
     * @return array List of warning messages (empty if all OK)
     */
    private function checkPhpLimits(): array
    {
        $warnings = [];
        if (!class_exists('ZipArchive')) {
            $warnings[] = 'Die PHP-Erweiterung zip (ZipArchive) ist nicht verfügbar.';
        }
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            $warnings[] = 'Weder cURL noch allow_url_fopen stehen für HTTPS-Downloads zur Verfügung.';
        }

        return $warnings;
    }

    /**
     * Parse PHP ini size values (e.g. "128M", "1G", "512K") to bytes
     */
    private function parsePhpSize(string $size): int
    {
        $size = trim($size);
        if ($size === '-1') return -1;
        if ($size === '0') return 0;

        $value = (int)$size;
        $unit = strtoupper(substr($size, -1));

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Download and apply update
     * 
     * @param string $downloadUrl URL to download the update from
     * @param string $newVersion Version being installed
     * @param bool $isPreRelease Whether the new version is a pre-release
     * @return array Result of the update operation
     */
    public function downloadAndApplyUpdate(
        string $downloadUrl,
        string $newVersion,
        bool $isPreRelease = false,
        ?string $expectedSha256 = null
    ): array
    {
        try {
            // Security: Validate download URL is from GitHub (zipball or release asset)
            $downloadKind = $this->source->downloadKind($downloadUrl);
            if ($downloadKind === null) {
                // Backwards compatibility: old updater versions only accept zipball URLs.
                // If this is a release asset URL that fails validation on an old install,
                // the calling code should retry with download_url_fallback (zipball_url).
                throw new Exception('Ungültige Download-URL. Updates können nur von GitHub heruntergeladen werden. URL: ' . substr($downloadUrl, 0, 100));
            }
            $isReleaseAsset = $downloadKind === 'asset';

            // Security: Validate version format
            if (!VersionComparator::isValidFormat($newVersion)) {
                throw new Exception('Ungültiges Versionsformat.');
            }

            $expectedSha256 = UpdateArchive::normalizeChecksum($expectedSha256);

            $appRoot = $this->appRoot;

            // Check write permissions
            if (!is_writable($appRoot)) {
                throw new Exception('Keine Schreibberechtigung für das Anwendungsverzeichnis. Bitte Dateiberechtigungen prüfen.');
            }

            // Nur echte technische Voraussetzungen blockieren. Speicher-,
            // Upload- und POST-Limits sind durch den gestreamten Server-
            // Download nicht relevant.
            $phpWarnings = $this->checkPhpLimits();
            if (!empty($phpWarnings)) {
                throw new Exception("PHP-Konfiguration unzureichend für Update:\n" . implode("\n", $phpWarnings));
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

            // Step 1: Download update directly to storage/temp. This avoids
            // holding the complete vendor-containing release in memory.
            $downloadSize = $this->source->download($downloadUrl, $zipFile);

            UpdateArchive::verify($zipFile, $expectedSha256);

            // Step 2: Extract ZIP
            $sourceDir = UpdateArchive::extract($zipFile, $extractDir);
            UpdateArchive::validateSharedPackages($sourceDir, $appRoot, $isReleaseAsset);

            // Release assets include vendor/ — source zipballs do not.
            $excludeDirs = $isReleaseAsset
                ? ['storage', 'system/updates']
                : ['vendor', 'storage', 'system/updates'];
            $excludeFiles = ['.env', '.git', '.gitignore'];
            $preserveDirs = ['assets/img'];

            // Step 3: Back up every existing file that the release can
            // overwrite. The former hard-coded src/assets/api list missed
            // templates, routes, config and vendor, making restoration partial.
            $backups = new BackupManager($appRoot);
            $backupDir = $backups->reserve();
            $backupSummary = $backups->backUp($backupDir, $sourceDir, $excludeDirs, $excludeFiles, $this->versionFile);

            // Step 4: Apply update (copy files)
            $installer = new FileInstaller($appRoot);
            try {
                $installer->copy($sourceDir, $excludeDirs, $excludeFiles, $preserveDirs);
            } catch (Exception $e) {
                throw new Exception('Fehler beim Kopieren der Update-Dateien: ' . $e->getMessage() . ' - Backup verfügbar in: ' . $backupDir);
            }

            // Step 4.5: Delete-Manifest abarbeiten (entfernt Ordner/Dateien, die
            // mit der neuen Version wegfallen sollen — z.B. Modul-Verzeichnisse
            // nach einer Router-Migration). Fehlendes Manifest ist kein Fehler.
            try {
                $manifestResult = $installer->applyManifest($sourceDir);
                if ($manifestResult['applied']) {
                    \App\Logging\Logger::info('Update-Manifest verarbeitet', [
                        'deleted' => $manifestResult['deleted'],
                        'skipped' => $manifestResult['skipped'],
                    ]);
                } elseif (!empty($manifestResult['error'])) {
                    \App\Logging\Logger::warning('Update-Manifest-Fehler: ' . $manifestResult['error']);
                }
            } catch (\Throwable $e) {
                \App\Logging\Logger::warning('Update-Manifest konnte nicht angewendet werden: ' . $e->getMessage());
            }

            // Step 5: Update version.json
            if (!$this->updateVersionFile([
                'version' => $newVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->versions->current()['build_number'] ?? 0) + 1,
                'commit_hash' => 'auto-update',
                'prerelease' => $isPreRelease
            ])) {
                throw new Exception('Konnte version.json nicht aktualisieren. Update möglicherweise unvollständig.');
            }

            // Step 6: Mark composer as pending (only for zipball updates without vendor/)
            $composerPending = false;
            if (!$isReleaseAsset) {
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
                    \App\Logging\Logger::warning('Warning: Could not write composer pending file: ' . $this->composerPendingFile);
                }
                $composerPending = true;
            }

            // Step 7: Clear cache
            $this->clearCache();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            // Clean up temp files
            FileInstaller::deleteTree($tempDir);

            return [
                'success' => true,
                'message' => $composerPending
                    ? 'Update erfolgreich installiert! Composer-Abhängigkeiten werden jetzt aktualisiert...'
                    : 'Update erfolgreich auf ' . $newVersion . ' installiert!',
                'version' => $newVersion,
                'backup_dir' => $backupDir,
                'backup_files' => $backupSummary['backed_up_files'],
                'composer_pending' => $composerPending,
                'download_size' => $downloadSize,
                'integrity_verified' => $expectedSha256 !== null,
            ];
        } catch (Exception $e) {
            // Clean up temp files if they exist
            if (isset($tempDir) && is_dir($tempDir)) {
                try {
                    FileInstaller::deleteTree($tempDir);
                } catch (Exception $cleanupEx) {
                    // Ignore cleanup errors
                }
            }

            // Run comprehensive diagnostics
            $report = $this->diagnostics->report($e, [
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
            ] + $report;
        }
    }

    /**
     * Run composer install after system update
     * 
     * @param string $appRoot Application root directory
     * @return array Result containing execution status and output
     */
    private function runComposerInstall(string $appRoot): array
    {
        // exec() steht in disable_functions vieler Shared-Hosting-Setups —
        // seit PHP 8 wirft der Aufruf dann einen fatalen Error statt still
        // zu scheitern. Ohne exec() kann Composer hier nicht laufen.
        if (!function_exists('exec')) {
            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'exec() ist auf diesem Hosting deaktiviert (disable_functions) — bitte `composer install --no-dev` manuell ausführen oder ein Release-Paket mit vendor/ verwenden.'
            ];
        }

        // Check if composer is available
        $composerPath = ComposerRunner::findExecutable();

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
                $report = $this->diagnostics->report(
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
                ] + $report;
            }
        } catch (Exception $e) {
            // Run diagnostics for exception
            $report = $this->diagnostics->report($e, [
                'operation' => 'composer_install_exception',
                'composer_path' => $composerPath
            ]);

            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Ausführen von Composer: ' . $e->getMessage(),
            ] + $report;
        }
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

        $appRoot = $this->appRoot;

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
        return $this->versions->write($versionData);
    }

    /**
     * Get all available releases from GitHub
     * 
     * @param int $limit Maximum number of releases to fetch
     */
    public function getAllReleases(int $limit = 10): array
    {
        return $this->source->releases($limit);
    }

    /**
     * Check if current version is a pre-release (beta, alpha, rc)
     * First checks the prerelease flag in version.json, then falls back to version string pattern matching
     */
    public function isPreRelease(): bool
    {
        return $this->versions->isPreRelease();
    }

    /**
     * Check if a specific version string is a pre-release
     * 
     * @param string $version Version string to check
     * @return bool True if version is a pre-release
     */
    public function isVersionPreRelease(string $version): bool
    {
        return VersionComparator::isPreRelease($version);
    }

    /**
     * Get version age in days
     */
    public function getVersionAge(): int
    {
        return $this->versions->ageInDays();
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
    public function getUpdateUrgency(?array $updateInfo = null): string
    {
        $updateInfo ??= $this->checkForUpdatesCached();

        if (!($updateInfo['available'] ?? false)) {
            return 'none';
        }

        return VersionComparator::urgency($this->versions->current()['version'], $updateInfo['latest_version'], $this->getVersionAge());
    }

    /**
     * Get formatted release notes as HTML
     */
    public function getFormattedReleaseNotes(string $markdown): string
    {
        return ReleaseNotes::toHtml($markdown);
    }

    private function updateCacheChannel(?bool $includePreRelease): string
    {
        $resolved = $includePreRelease ?? $this->isPreRelease();
        return $resolved ? 'prerelease' : 'stable';
    }

    /**
     * Check for updates with caching support
     * 
     * @param bool $forceRefresh If true, bypass cache and fetch fresh data
     * @param bool $includePreRelease If true, include pre-release versions in the check
     */
    public function checkForUpdatesCached(bool $forceRefresh = false, ?bool $includePreRelease = null): array
    {
        $channel = $this->updateCacheChannel($includePreRelease);
        $installed = $this->versions->current()['version'] ?? null;

        if (!$forceRefresh) {
            $cached = $this->cache->get($channel, $installed);

            if ($cached !== null) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $result = $this->checkForUpdates($includePreRelease);
        if (!isset($result['error'])) {
            $this->cache->put($channel, $installed ?? 'unknown', $result);
        } else {
            // GitHub-/Netzwerkfehler dürfen eine zuletzt bekannte Meldung
            // nicht vernichten. Für Managed Hosting ist ein markierter,
            // veralteter Status hilfreicher als gar kein Status.
            $stale = $this->cache->get($channel, $installed, true);
            if ($stale !== null) {
                $stale['cached'] = true;
                $stale['stale'] = true;
                $stale['refresh_error'] = $result['message'] ?? 'Update-Check fehlgeschlagen.';
                return $stale;
            }
        }
        $result['cached'] = false;

        return $result;
    }

    /**
     * Clear the update check cache
     */
    public function clearCache(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Fetch all branches from GitHub API
     *
     * @return array List of branch names
     */
    public function fetchBranches(): array
    {
        return $this->source->branches();
    }

    /**
     * Fetch the latest commit of a specific branch from GitHub API
     *
     * @param string $branch Branch name
     * @return array|null Commit info or null on error
     */
    public function fetchBranchLatestCommit(string $branch): ?array
    {
        return $this->source->branchLatestCommit($branch);
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
        $downloadUrl = $this->source->zipballUrl($commitSha);

        // Use a dev version string: branch-shortsha
        $shortSha = substr($commitSha, 0, 8);
        $devVersion = "dev-{$branch}-{$shortSha}";

        $result = $this->downloadAndApplyUpdate($downloadUrl, $devVersion, true);

        // Update version.json with the full commit hash for proper detection
        if ($result['success']) {
            $this->updateVersionFile([
                'version' => $devVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->versions->current()['build_number'] ?? 0) + 1,
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
            $appRoot = $this->appRoot;
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
                        FileInstaller::deleteTree($dir);
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
        return $this->diagnostics->run($exception, $context);
    }

    /**
     * Get the latest diagnostic report
     */
    public function getLatestDiagnosticReport(): ?array
    {
        return $this->diagnostics->latestReport();
    }

    /**
     * Format diagnostic summary as HTML for UI display
     */
    public function formatDiagnosticHTML(array $diagnostics): string
    {
        return DiagnosticFormatter::html($diagnostics);
    }

    /**
     * Generate support export text (easy to copy/paste)
     */
    public function formatDiagnosticForSupport(array $diagnostics): string
    {
        return DiagnosticFormatter::support($diagnostics);
    }
}
