<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use Exception;

/**
 * Diagnose für fehlgeschlagene Updates: sammelt Umgebung, Rechte,
 * Speicher, Netz und Update-Verlauf, ordnet den Fehler ein und legt den
 * Bericht unter storage/logs ab.
 */
final class UpdateDiagnostics
{
    public function __construct(
        private readonly string $appRoot,
        private readonly string $diagnosticFile,
        private readonly VersionStore $versions,
        private readonly GitHubReleaseSource $source,
    ) {
    }

    /**
     * Diagnose samt den drei Darstellungen, die Fehlerantworten mitgeben.
     *
     * @param array<string, mixed> $context
     * @return array{diagnostics: array<string, mixed>, diagnostic_summary: string, diagnostic_html: string, diagnostic_support: string}
     */
    public function report(Exception $exception, array $context): array
    {
        $diagnostics = $this->run($exception, $context);

        return [
            'diagnostics' => $diagnostics,
            'diagnostic_summary' => DiagnosticFormatter::summary($diagnostics),
            'diagnostic_html' => DiagnosticFormatter::html($diagnostics),
            'diagnostic_support' => DiagnosticFormatter::support($diagnostics),
        ];
    }

    /**
     * Comprehensive diagnostic function for update failures
     * 
     * @param Exception|null $exception Optional exception that triggered the diagnostic
     * @param array<string, mixed> $context Additional context information about the failure
     * @return array<string, mixed> Detailed diagnostic report for support analysis
     */
    public function run(?Exception $exception = null, array $context = []): array
    {
        $appRoot = $this->appRoot;

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
        $diagnostics['severity'] = self::severity($diagnostics);

        // Save diagnostic report to file
        $this->saveDiagnosticReport($diagnostics);

        return $diagnostics;
    }

    /**
     * Diagnose system environment (PHP version, extensions, settings)
     *
     * @return array<string, mixed>
     */
    private function diagnoseSystemEnvironment(): array
    {
        $requiredExtensions = ['curl', 'zip', 'json', 'mbstring', 'openssl'];
        $recommendedExtensions = ['fileinfo', 'dom', 'xml'];

        $loadedExtensions = get_loaded_extensions();
        $missingRequired = array_diff($requiredExtensions, $loadedExtensions);
        $missingRecommended = array_diff($recommendedExtensions, $loadedExtensions);

        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes((string) $memoryLimit);
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
     *
     * @return array<string, mixed>
     */
    private function diagnoseFilePermissions(string $appRoot): array
    {
        $criticalPaths = [
            'root' => $appRoot,
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
     *
     * @return array<string, mixed>
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
        $backupSize = $this->getDirectorySize($appRoot . '/storage/backups/updates');

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
     *
     * @return array<string, mixed>
     */
    private function diagnoseNetworkConnectivity(): array
    {
        $tests = [];
        $overallStatus = 'ok';

        // Test 1: GitHub API connectivity
        $apiTest = $this->source->probe();
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
     * Test SSL/TLS configuration
     *
     * @return array<string, mixed>
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
            // Ohne beides ist die Methode oben schon mit 'error' zurück.
            'status' => 'ok'
        ];
    }

    /**
     * Test DNS resolution
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
     */
    private function diagnoseDependencies(): array
    {
        $composerPath = ComposerRunner::findExecutable();
        $composerAvailable = !empty($composerPath);
        $composerVersion = null;

        if ($composerAvailable && function_exists('exec')) {
            $output = [];
            $returnCode = 0;
            @exec(escapeshellarg($composerPath) . ' --version 2>&1', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                if (preg_match('/Composer version ([0-9.]+)/', implode(' ', $output), $matches)) {
                    $composerVersion = $matches[1];
                }
            }
        }

        $appRoot = $this->appRoot;
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
     *
     * @return array<string, mixed>
     */
    private function diagnoseUpdateHistory(): array
    {
        $appRoot = $this->appRoot;
        $updatesDir = $appRoot . '/storage/backups/updates';

        $backups = [];
        if (is_dir($updatesDir)) {
            $backupDirs = glob($updatesDir . '/backup_*', GLOB_ONLYDIR);
            foreach ($backupDirs as $dir) {
                $backups[] = [
                    'name' => basename($dir),
                    'path' => $dir,
                    'created' => date('Y-m-d H:i:s', (int) filemtime($dir)),
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
                    'created' => date('Y-m-d H:i:s', (int) filemtime($dir)),
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
            'current_version' => $this->versions->current(),
            'version_age_days' => $this->versions->ageInDays(),
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
     *
     * @return array<string, mixed>
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
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
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
        $errorType = self::classifyError($message);
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
    public static function classifyError(string $message): string
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
     *
     * @param array<string, mixed> $context
     * @return list<string>
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
     *
     * @param array<string, mixed> $context
     * @return list<string>
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
                'Speicherplatz freigeben: storage/temp/* und alte storage/backups/updates/backup_* löschen',
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
                'Alte Backups manuell wiederherstellen aus storage/backups/updates/backup_*',
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
     *
     * @param array<string, mixed> $diagnostics
     */
    public static function severity(array $diagnostics): string
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
     *
     * @param array<string, mixed> $diagnostics
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
            $jsonFiles = glob($this->reportPattern());
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
     *
     * @return array<string, mixed>|null
     */
    public function latestReport(): ?array
    {
        $jsonFiles = glob($this->reportPattern());
        if (empty($jsonFiles)) {
            return null;
        }

        usort($jsonFiles, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $latestFile = $jsonFiles[0];
        $content = file_get_contents($latestFile);
        return json_decode((string) $content, true);
    }

    /**
     * Die JSON-Berichte heißen wie das Log, nur mit Zeitstempel:
     * updater-diagnostic.log → updater-diagnostic_20261001_120000.json.
     */
    private function reportPattern(): string
    {
        return dirname($this->diagnosticFile) . '/' . basename($this->diagnosticFile, '.log') . '_*.json';
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
}
