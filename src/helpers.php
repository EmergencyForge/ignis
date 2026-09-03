<?php

/**
 * intraRP — Globale Helper-Funktionen
 *
 * Wird via composer "files"-autoload bei jedem Request automatisch geladen.
 */

declare(strict_types=1);

use Psr\Container\ContainerInterface;

if (!function_exists('app')) {
    /**
     * Service-Container-Accessor.
     *
     *     app()                   → Container-Instanz
     *     app(SomeClass::class)   → aufgelöste Instanz
     *
     * Wirft \RuntimeException, falls der Container vor Bootstrap aufgerufen
     * wird (passiert nur, wenn assets/config/config.php nicht durchlief).
     *
     * @template T of object
     * @param class-string<T>|null $abstract
     * @return ($abstract is null ? ContainerInterface : T)
     */
    function app(?string $abstract = null)
    {
        $container = $GLOBALS['app_container'] ?? null;
        if (!$container instanceof ContainerInterface) {
            throw new \RuntimeException(
                'Service container not initialized. '
                . 'Stelle sicher, dass assets/config/config.php geladen wurde.'
            );
        }
        if ($abstract === null) {
            return $container;
        }
        return $container->get($abstract);
    }
}

if (!function_exists('asset')) {
    /**
     * Baut eine Asset-URL mit automatischem Cache-Buster-Query.
     *
     * Hängt `?v=<mtime>` an, damit Browser nach einem Deploy die neue
     * Datei ziehen, ohne dass der User manuell einen Hard-Reload machen
     * muss. Existiert die Datei nicht, entfällt der Query-String.
     *
     *     asset('public/assets/dist/vendor.css')
     *     → /assets/dist/vendor.css?v=1713456789
     *
     * Das Docroot ist public/. Ein führendes `public/` im Pfad fällt
     * deshalb aus der URL heraus; die Datei wird für den Cache-Buster
     * zuerst unter public/ gesucht (dort landen auch die vom Build
     * gespiegelten assets/img, assets/js usw.) und sonst im Projekt-Root
     * (Plugin-Assets, die eine Route ausliefert).
     *
     * BASE_PATH wird vorangestellt, damit Subdirectory-Installs
     * (`/intrarp/abc/…`) automatisch korrekt verlinkt werden.
     */
    function asset(string $path): string
    {
        $relPath = ltrim($path, '/');
        if (str_starts_with($relPath, 'public/')) {
            $relPath = substr($relPath, strlen('public/'));
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $root = dirname(__DIR__);

        $version = 0;
        foreach ([$root . '/public/' . $relPath, $root . '/' . $relPath] as $absolute) {
            if (is_file($absolute)) {
                $version = (int) filemtime($absolute);
                break;
            }
        }

        $url = rtrim($base, '/') . '/' . $relPath;
        return $version > 0 ? $url . '?v=' . $version : $url;
    }
}
