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
 * Update-Prüfung und -Installation. Die Klasse hält nur den Ablauf
 * zusammen; die Arbeit machen die Bausteine in App\Utils\Updater
 * (GitHub-Zugriff, Archivprüfung, Backup, Kopieren, version.json, Cache,
 * Composer, Diagnose). Die öffentlichen Methoden bleiben die, die
 * Einstellungsseiten, API und Konsole aufrufen.
 */
class SystemUpdater
{
    private string $versionFile;
    private string $appRoot;
    private GitHubReleaseSource $source;
    private VersionStore $versions;
    private UpdateCheckCache $cache;
    private UpdateDiagnostics $diagnostics;
    private ComposerRunner $composer;
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
        $this->diagnosticFile = $appRoot . '/storage/logs/updater-diagnostic.log';
        LegacyStorageMigration::run($appRoot, [
            '/version.json'          => $this->versionFile,
            '/composer_pending.json' => $appRoot . '/storage/composer_pending.json',
            '/diagnostic.log'        => $this->diagnosticFile,
        ]);
        $this->versions = new VersionStore($this->versionFile);
        $this->cache = new UpdateCheckCache($appRoot . '/storage/cache/update-check.json');
        $this->diagnostics = new UpdateDiagnostics($appRoot, $this->diagnosticFile, $this->versions, $this->source);
        $this->composer = new ComposerRunner($appRoot, $appRoot . '/storage/composer_pending.json', $this->diagnostics);
        $this->cleanupOldTempDirectories();
    }

    /**
     * Get current version information
     *
     * @return array<string, mixed>
     */
    public function getCurrentVersion(): array
    {
        return $this->versions->current();
    }

    /**
     * Check for available updates from GitHub releases
     * 
     * @param bool $includePreRelease If true, include pre-release versions in the check
     * @return array<string, mixed>
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
     *
     * @return list<string> List of warning messages (empty if all OK)
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
     * Download and apply update
     * 
     * @param string $downloadUrl URL to download the update from
     * @param string $newVersion Version being installed
     * @param bool $isPreRelease Whether the new version is a pre-release
     * @return array<string, mixed> Result of the update operation
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
            // Ab hier liegen auf der Platte die Dateien der neuen Version. Was
            // danach noch geladen würde, käme von dort und passt vielleicht
            // nicht mehr zu diesem Code; der Fehlerbericht wird deshalb vorher geladen.
            class_exists(DiagnosticFormatter::class);
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
                $this->composer->markPending($newVersion);
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
     * Check if composer installation is pending
     * 
     * @return array<string, mixed> Status information
     */
    public function getComposerStatus(): array
    {
        return $this->composer->status();
    }

    /**
     * Execute pending composer installation
     * 
     * @return array<string, mixed> Result of composer execution
     */
    public function executePendingComposerInstall(): array
    {
        return $this->composer->installPending();
    }

    /**
     * Update version.json file
     * 
     * @param array<string, mixed> $versionData New version data
     */
    public function updateVersionFile(array $versionData): bool
    {
        return $this->versions->write($versionData);
    }

    /**
     * Get all available releases from GitHub
     * 
     * @param int $limit Maximum number of releases to fetch
     * @return array<mixed>
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
     *
     * @param array<string, mixed> $updateInfo
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
     * @return array<string, mixed>
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
     * @return array<mixed> List of branch names
     */
    public function fetchBranches(): array
    {
        return $this->source->branches();
    }

    /**
     * Fetch the latest commit of a specific branch from GitHub API
     *
     * @param string $branch Branch name
     * @return array<string, mixed>|null Commit info or null on error
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
     * @return array<string, mixed> Result of the update operation
     */
    public function downloadAndApplyBranchUpdate(string $branch, string $commitSha): array
    {
        // Construct the zipball URL for the specific commit
        $downloadUrl = $this->source->zipballUrl($commitSha);

        // Use a dev version string: branch-shortsha
        $shortSha = substr($commitSha, 0, 8);
        $devVersion = "dev-{$branch}-{$shortSha}";

        $result = $this->downloadAndApplyUpdate($downloadUrl, $devVersion, true);

        // Update version.json with the full commit hash for proper detection.
        // Die Build-Nummer hat downloadAndApplyUpdate() schon hochgezählt.
        if ($result['success']) {
            $this->updateVersionFile([
                'version' => $devVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->versions->current()['build_number'] ?? 0),
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
     * @param array<string, mixed> $context Additional context information about the failure
     * @return array<string, mixed> Detailed diagnostic report for support analysis
     */
    public function runUpdateDiagnostics(?Exception $exception = null, array $context = []): array
    {
        return $this->diagnostics->run($exception, $context);
    }

    /**
     * Get the latest diagnostic report
     *
     * @return array<string, mixed>|null
     */
    public function getLatestDiagnosticReport(): ?array
    {
        return $this->diagnostics->latestReport();
    }

    /**
     * Format diagnostic summary as HTML for UI display
     *
     * @param array<string, mixed> $diagnostics
     */
    public function formatDiagnosticHTML(array $diagnostics): string
    {
        return DiagnosticFormatter::html($diagnostics);
    }

    /**
     * Generate support export text (easy to copy/paste)
     *
     * @param array<string, mixed> $diagnostics
     */
    public function formatDiagnosticForSupport(array $diagnostics): string
    {
        return DiagnosticFormatter::support($diagnostics);
    }
}
