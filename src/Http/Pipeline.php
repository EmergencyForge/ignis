<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\MiddlewareInterface;
use Psr\Container\ContainerInterface;

/**
 * Middleware-Pipeline: führt eine Liste von Middlewares sequentiell aus und
 * ruft zum Schluss den eigentlichen Route-Handler auf.
 *
 * Middlewares werden von außen nach innen aufgerufen — der erste Eintrag
 * der Liste ist der "äußerste" und sieht den Request zuerst bzw. die Response
 * zuletzt.
 *
 * Middleware-Einträge können sein:
 *   - MiddlewareInterface-Instanz
 *   - class-string (wird über den Container aufgelöst)
 *   - "ClassName:argA,argB" — Kurz-Syntax für parametrisierte Middleware,
 *     die Argumente werden als weitere Constructor-Parameter gereicht
 *     (nur String-Argumente — für komplexere Fälle: Instanz direkt übergeben)
 */
final class Pipeline
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param  array<int, MiddlewareInterface|string>  $middlewares
     * @param  callable(Request): Response             $handler
     */
    public function run(Request $request, array $middlewares, callable $handler): Response
    {
        // Pipeline rückwärts zu einer verschachtelten Closure aufbauen —
        // Standard-Onion-Pattern. Das äußerste Middleware wird zuletzt
        // umhüllt und damit als erstes aufgerufen.
        $next = $handler;

        foreach (array_reverse($middlewares) as $mw) {
            $instance = $this->resolve($mw);
            $current  = $next;
            $next = static function (Request $req) use ($instance, $current): Response {
                return $instance->process($req, $current);
            };
        }

        return $next($request);
    }

    private function resolve(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // "Class:arg1,arg2" — parametrisierte Form
        if (str_contains($middleware, ':')) {
            [$class, $argString] = explode(':', $middleware, 2);
            $args = array_map('trim', explode(',', $argString));
            /** @var class-string<MiddlewareInterface> $class */
            return new $class(...$args);
        }

        /** @var MiddlewareInterface $instance */
        $instance = $this->container->get($middleware);
        return $instance;
    }
}
