<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Composer auf dem Server: finden und ausführen.
 */
final class ComposerRunner
{
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
}
