<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use App\Logging\Logger;
use Exception;

/**
 * Composer auf dem Server: finden und ausführen. Nach einem Quellupdate
 * ohne vendor/ merkt sich storage/composer_pending.json, dass
 * `composer install` noch aussteht.
 */
final class ComposerRunner
{
    public function __construct(
        private readonly string $appRoot,
        private readonly string $pendingFile,
        private readonly UpdateDiagnostics $diagnostics,
    ) {
    }

    /**
     * Merkt vor, dass nach dem Update `composer install` laufen muss.
     */
    public function markPending(string $version): void
    {
        $composerStatus = [
            'pending' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'version' => $version
        ];

        $dir = dirname($this->pendingFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!file_put_contents($this->pendingFile, json_encode($composerStatus, JSON_PRETTY_PRINT))) {
            Logger::warning('Warning: Could not write composer pending file: ' . $this->pendingFile);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        if (!file_exists($this->pendingFile)) {
            return [
                'pending' => false,
                'message' => 'Keine ausstehende Composer-Installation.'
            ];
        }

        $content = file_get_contents($this->pendingFile);
        $status = json_decode((string) $content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Corrupted file, remove it and return not pending
            if (!unlink($this->pendingFile)) {
                Logger::warning('Warning: Could not remove corrupted composer pending file: ' . $this->pendingFile);
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
     * @return array<string, mixed>
     */
    public function installPending(): array
    {
        if (!file_exists($this->pendingFile)) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Keine ausstehende Composer-Installation gefunden.'
            ];
        }

        $result = $this->install();

        // Remove pending status file if successful
        if ($result['success']) {
            if (!unlink($this->pendingFile)) {
                Logger::warning('Warning: Could not remove composer pending file after successful install: ' . $this->pendingFile);
            }
        }

        return $result;
    }

    /**
     * Find composer executable on the system
     * 
     * @return string|null Path to composer executable or null if not found
     */
    public static function findExecutable(): ?string
    {
        // Try absolute paths first, but only if open_basedir allows access
        $absolutePaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer'
        ];

        foreach ($absolutePaths as $path) {
            try {
                if (@file_exists($path) && @is_executable($path)) {
                    return $path;
                }
            } catch (\Exception $e) {
                // open_basedir restriction — skip this path
                continue;
            }
        }

        // For composer in PATH, use which/where command with strict validation.
        // Without exec() (disable_functions) the PATH probe is impossible —
        // the absolute-path candidates above remain the only option then.
        if (!function_exists('exec')) {
            return null;
        }

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
                    $realPath = @realpath($execPath);

                    // Verify it's a real file, executable, and in safe directories
                    if ($realPath && @file_exists($realPath) && @is_executable($realPath)) {
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
     * Run composer install after system update
     * 
     * @return array<string, mixed> Result containing execution status and output
     */
    private function install(): array
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
        $composerPath = self::findExecutable();

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
                    escapeshellarg($this->appRoot)
                );
            } else {
                $command = sprintf(
                    'timeout 600 %s install --working-dir=%s --no-dev --optimize-autoloader --no-interaction 2>&1',
                    escapeshellarg($composerPath),
                    escapeshellarg($this->appRoot)
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
}
