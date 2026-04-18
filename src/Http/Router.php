<?php

declare(strict_types=1);

namespace App\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;

use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

/**
 * intraRP-Router — Fassade über nikic/fast-route.
 *
 * Verantwortlichkeiten:
 *   1. Route-Definitionen sammeln (Methoden `get()`, `post()`, `group()`, …)
 *   2. Für jede Route einen Middleware-Stack und einen Handler speichern
 *   3. Bei dispatch() den eingehenden Request matchen und die Pipeline
 *      durchlaufen lassen
 *
 * Design-Entscheidung: Es gibt bewusst kein Chaining à la Laravel
 * (`Route::get(...)->middleware(...)`) — stattdessen nimmt jede Route-
 * Methode das Middleware-Array direkt als Parameter. Das bleibt explizit
 * und testbar, und ist für einen Pattern-Port auf einer Webspace-Codebase
 * völlig ausreichend.
 *
 * Handler-Definition kann sein:
 *   - Closure: fn(Request $r) => Response
 *   - [ControllerClass::class, 'method'] — wird via Container aufgelöst
 *   - "ControllerClass@method" — String-Kurzform
 */
final class Router
{
    /**
     * @var array<int, array{
     *     methods: array<int,string>,
     *     path: string,
     *     handler: mixed,
     *     middleware: array<int, string|Middleware\MiddlewareInterface>,
     * }>
     */
    private array $routes = [];

    /** @var array<int, array<int, string|Middleware\MiddlewareInterface>> */
    private array $groupMiddlewareStack = [];

    /** @var array<int, string> */
    private array $groupPrefixStack = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Pipeline $pipeline,
        /**
         * Cache-Verhalten für den FastRoute-Dispatcher.
         *   - `true`  (Default): File-Cache unter `storage/cache/routes.php`,
         *                        mit mtime-basierter Auto-Invalidation.
         *   - `false`:            Kein Cache — jede Request baut den Dispatcher
         *                        frisch. Tests nutzen das, damit das Live-Cache-
         *                        File (mit Produktions-Routen) die Test-Router-
         *                        Instanzen nicht verfälscht.
         */
        private readonly bool $enableCache = true,
    ) {}

    // ── Route-Registrierung ───────────────────────────────────────────

    /**
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute(['GET'], $path, $handler, $middleware);
    }

    /**
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute(['PUT'], $path, $handler, $middleware);
    }

    /**
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute(['DELETE'], $path, $handler, $middleware);
    }

    /**
     * @param  array<int,string>                                  $methods
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    public function match(array $methods, string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute($methods, $path, $handler, $middleware);
    }

    /**
     * Gruppiert Routen mit einem gemeinsamen Prefix und/oder Middleware-Stack.
     * Der Inner-Callback bekommt `$this` gereicht, damit dort weitere
     * `->get(...)` etc. direkt registriert werden können.
     *
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     * @param  callable(self): void                               $register
     */
    public function group(string $prefix, array $middleware, callable $register): void
    {
        $this->groupPrefixStack[]     = rtrim($prefix, '/');
        $this->groupMiddlewareStack[] = $middleware;

        try {
            $register($this);
        } finally {
            array_pop($this->groupPrefixStack);
            array_pop($this->groupMiddlewareStack);
        }
    }

    /**
     * @param  array<int,string>                                  $methods
     * @param  array<int, string|Middleware\MiddlewareInterface>  $middleware
     */
    private function addRoute(array $methods, string $path, mixed $handler, array $middleware): void
    {
        $prefix = implode('', $this->groupPrefixStack);
        $fullPath = $prefix . '/' . ltrim($path, '/');
        $fullPath = '/' . ltrim($fullPath, '/');

        $stack = [];
        foreach ($this->groupMiddlewareStack as $groupStack) {
            foreach ($groupStack as $mw) {
                $stack[] = $mw;
            }
        }
        foreach ($middleware as $mw) {
            $stack[] = $mw;
        }

        $this->routes[] = [
            'methods'    => $methods,
            'path'       => $fullPath,
            'handler'    => $handler,
            'middleware' => $stack,
        ];
    }

    // ── Dispatching ───────────────────────────────────────────────────

    public function dispatch(Request $request): Response
    {
        $dispatcher = $this->buildDispatcher();
        $info       = $dispatcher->dispatch($request->method, $request->path);

        switch ($info[0]) {
            case Dispatcher::NOT_FOUND:
                return Response::text('Not Found', 404);

            case Dispatcher::METHOD_NOT_ALLOWED:
                /** @var array<int,string> $allowed */
                $allowed = $info[1];
                return (new Response(405, 'Method Not Allowed'))
                    ->withHeader('Allow', implode(', ', $allowed));

            case Dispatcher::FOUND:
                /** @var array{0:int,1:int,2:array<string,string>} $info */
                // FastRoute liefert den Routen-Index zurück (Handler-Kennung) —
                // wir schlagen die echte Route-Definition (mit Closure, Middleware)
                // aus $this->routes nach. Indirektion ist nötig, weil Closures
                // nicht serialisiert werden können (Cache-Kompatibilität).
                $routeIdx = $info[1];
                $routeDef = $this->routes[$routeIdx];
                /** @var array<string,string> $params */
                $params   = $info[2];

                $handler = $this->buildHandlerCallable($routeDef['handler'], $params);

                // Route-Parameter als Attribute in den Request schieben,
                // damit Middlewares sie auch sehen (z.B. Policy-Resolver).
                foreach ($params as $k => $v) {
                    $request = $request->withAttribute($k, $v);
                }

                return $this->pipeline->run($request, $routeDef['middleware'], $handler);
        }

        return Response::text('Internal Router Error', 500);
    }

    private function buildDispatcher(): Dispatcher
    {
        $routeCallback = function (RouteCollector $rc): void {
            foreach ($this->routes as $idx => $route) {
                // Nur den Index in den Cache — die Route-Definition enthält
                // Closures und Middleware-Instances, die nicht serialisierbar sind.
                // Die echte Definition wird bei dispatch() via $this->routes[$idx]
                // nachgeschlagen.
                $rc->addRoute($route['methods'], $route['path'], $idx);
            }
        };

        if (!$this->enableCache) {
            // simpleDispatcher baut frisch, ohne File-Cache — notwendig für Tests,
            // da cachedDispatcher auch mit `cacheDisabled=true` eine cacheFile-
            // Option erzwingt (API-Quirk von FastRoute).
            return simpleDispatcher($routeCallback);
        }

        $cacheFile = dirname(__DIR__, 2) . '/storage/cache/routes.php';
        $this->invalidateStaleRouteCache($cacheFile);

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        return cachedDispatcher($routeCallback, [
            'cacheFile'     => $cacheFile,
            'cacheDisabled' => false,
        ]);
    }

    /**
     * Löscht den Route-Cache, wenn eine der Route-Definitions-Dateien neuer
     * ist als das Cache-File. Stellt sicher, dass Dev-Änderungen an routes/
     * im nächsten Request wirksam sind, ohne manuelles Cache-Clearing.
     */
    private function invalidateStaleRouteCache(string $cacheFile): void
    {
        if (!is_file($cacheFile)) {
            return;
        }

        $cacheMtime = (int) filemtime($cacheFile);
        $routesDir  = dirname(__DIR__, 2) . '/routes';
        foreach ((glob($routesDir . '/*.php') ?: []) as $routeFile) {
            if ((int) filemtime($routeFile) > $cacheMtime) {
                @unlink($cacheFile);
                return;
            }
        }
    }

    /**
     * @param  array<string,string>  $params
     * @return callable(Request): Response
     */
    private function buildHandlerCallable(mixed $handler, array $params): callable
    {
        // Closure: direkt durchreichen
        if ($handler instanceof \Closure) {
            return function (Request $req) use ($handler, $params): Response {
                $result = $handler($req, ...array_values($params));
                return $result instanceof Response ? $result : Response::empty();
            };
        }

        // String "Class@method"
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler = [$class, $method];
        }

        // [Class::class, 'method']
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            return function (Request $req) use ($class, $method, $params): Response {
                /** @var object $controller */
                $controller = $this->container->get($class);
                $args = array_values($params);
                // Request als erster Parameter, dann die Route-Parameter
                $result = $controller->{$method}($req, ...$args);
                return $result instanceof Response ? $result : Response::empty();
            };
        }

        throw new \InvalidArgumentException('Router: unsupported handler definition');
    }
}
