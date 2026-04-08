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
