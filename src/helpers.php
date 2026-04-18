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
     *     → /public/assets/dist/vendor.css?v=1713456789
     *
     * BASE_PATH wird vorangestellt, damit Subdirectory-Installs
     * (`/intrarp/abc/…`) automatisch korrekt verlinkt werden.
     */
    function asset(string $path): string
    {
        $relPath  = ltrim($path, '/');
        $base     = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $absolute = dirname(__DIR__) . '/' . $relPath;
        $version  = is_file($absolute) ? (int) filemtime($absolute) : 0;

        $url = rtrim($base, '/') . '/' . $relPath;
        return $version > 0 ? $url . '?v=' . $version : $url;
    }
}
