<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Einmalige Migration: /system/updates/* → /storage/*.
 * Das alte Verzeichnis wird anschließend entfernt, damit es nicht als
 * Stolperstein zurückbleibt.
 */
final class LegacyStorageMigration
{
    /**
     * @param array<string, string> $moves Dateiname im Altverzeichnis (mit führendem /) → neuer Pfad
     */
    public static function run(string $appRoot, array $moves): void
    {
        $legacyDir = $appRoot . '/system/updates';
        if (!is_dir($legacyDir)) {
            return;
        }

        foreach ($moves as $legacyName => $newPath) {
            $legacyPath = $legacyDir . $legacyName;
            if (!file_exists($legacyPath)) {
                continue;
            }
            $targetDir = dirname($newPath);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            if (!file_exists($newPath)) {
                @copy($legacyPath, $newPath);
            }
            @unlink($legacyPath);
        }

        // Alle weiteren Dateien im Legacy-Ordner ignorieren — sie waren
        // temporäre Artefakte. Ordner entfernen falls leer.
        @rmdir($legacyDir);
        @rmdir($appRoot . '/system');
    }
}
