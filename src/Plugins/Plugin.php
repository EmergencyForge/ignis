<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Ein auf der Platte entdecktes Plugin: sein Manifest plus der Ordner, in
 * dem es liegt. Über die Pfad-Helfer greifen der Loader und die
 * Register-Merger auf die einzelnen Fragmente zu (routes, navigation,
 * events, migrations, …).
 */
final class Plugin
{
    public function __construct(
        public readonly PluginManifest $manifest,
        public readonly string $directory,
    ) {}

    public function id(): string
    {
        return $this->manifest->id;
    }

    /**
     * Absoluter Pfad zu einer Datei im Plugin-Ordner, oder null wenn sie
     * nicht existiert. So können optionale Fragmente (ein Plugin ohne
     * Cron-Jobs hat keine cron.php) sauber übersprungen werden.
     */
    public function path(string $relative): ?string
    {
        $full = $this->directory . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
        return is_file($full) ? $full : null;
    }

    /**
     * Verzeichnis im Plugin, oder null wenn nicht vorhanden (z.B. migrations/).
     */
    public function dir(string $relative): ?string
    {
        $full = $this->directory . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
        return is_dir($full) ? $full : null;
    }
}
