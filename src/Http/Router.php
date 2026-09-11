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
 *
 * Haken (angehängt in App\Http\RouterFactory, der einzigen Stelle, an der
 * ein Router entsteht):
 *   - beforeDispatch(): läuft zu Beginn jedes dispatch(), etwa um
 *     Request-gebundene Zwischenspeicher zurückzusetzen
 *   - afterDispatch(): sieht jede Antwort (auch 404/405) und darf sie
 *     ersetzen, etwa um Redirects für fetch-Aufrufer umzuschreiben
 *
 * Eine App\Http\RedirectException aus einem Handler (Controller::redirect())
 * wird direkt am Handler zu Response::redirect(), siehe buildHandlerCallable().
 */
final class Router
{
    /** @var list<callable(Request): void> */
    private array $beforeDispatch = [];

    /** @var list<callable(Request, Response): Response> */
    private array $afterDispatch = [];

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

    /** @var list<string> Dateien, aus denen der Route-Satz stammt. */
    private array $routeSources = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Pipeline $pipeline,
        /**
         * Cache-Verhalten für den FastRoute-Dispatcher.
         *   - `true`  (Default): File-Cache unter `storage/cache/routes.php`,
         *                        verfällt automatisch, sobald sich die per
         *                        registerRouteSource() gemeldeten Quellen
         *                        ändern.
         *   - `false`:            Kein Cache — jede Request baut den Dispatcher
         *                        frisch. Tests nutzen das, damit das Live-Cache-
         *                        File (mit Produktions-Routen) die Test-Router-
         *                        Instanzen nicht verfälscht.
         */
        private readonly bool $enableCache = true,
        /**
         * Ablage des Route-Caches. null nimmt `storage/cache/routes.php`,
         * also den Ort, an dem er im Betrieb liegt. Tests setzen einen
         * eigenen Pfad, damit sie den echten Cache nicht anfassen.
         */
        private readonly ?string $cacheFile = null,
    ) {}

    // ── Haken ─────────────────────────────────────────────────────────

    /**
     * @param callable(Request): void $hook
     */
    public function beforeDispatch(callable $hook): void
    {
        $this->beforeDispatch[] = $hook;
    }

    /**
     * @param callable(Request, Response): Response $hook
     */
    public function afterDispatch(callable $hook): void
    {
        $this->afterDispatch[] = $hook;
    }

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
        foreach ($this->beforeDispatch as $hook) {
            $hook($request);
        }

        $response = $this->dispatchRoute($request);

        foreach ($this->afterDispatch as $hook) {
            $response = $hook($request, $response);
        }

        return $response;
    }

    private function dispatchRoute(Request $request): Response
    {
        $dispatcher = $this->buildDispatcher();
        $info       = $dispatcher->dispatch($request->method, $request->path);

        switch ($info[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->render404();

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

    /**
     * Rendert das 404-Template (templates/errors/404.php) und liefert es als
     * HTML-Response. Faellt auf einen Plain-Text-Body zurueck, falls das
     * Template fehlt — damit gibt's auch in einer kaputten Installation
     * immer einen 404-Status, nicht stillschweigend einen 500er.
     */
    private function render404(): Response
    {
        $template = dirname(__DIR__, 2) . '/templates/errors/404.php';
        if (!is_file($template)) {
            return Response::text('Not Found', 404);
        }

        ob_start();
        try {
            require $template;
            $body = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            return Response::text('Not Found', 404);
        }

        return Response::html($body, 404);
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

        $cacheFile = $this->cacheFile ?? dirname(__DIR__, 2) . '/storage/cache/routes.php';
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
     * Meldet eine Datei, aus der Routen dieses Routers stammen.
     *
     * public/index.php ruft das für jede Datei auf, die es einliest — die
     * Kern-Routen und die Fragmente der aktiven Plugins. Der Router kennt
     * damit die Quellen seines Route-Satzes, ohne selbst etwas über Plugins
     * zu wissen.
     */
    public function registerRouteSource(string $file): void
    {
        $this->routeSources[] = $file;
    }

    /**
     * Verwirft den Route-Cache, sobald sich der Satz der Quelldateien oder
     * deren Inhalt geändert hat.
     *
     * Verglichen wird ein Fingerabdruck über alle gemeldeten Quellen, nicht
     * nur deren Alter. Ein reiner mtime-Vergleich sieht drei der vier Fälle
     * nicht, die im Betrieb vorkommen:
     *
     *   - Ein Plugin wird im Panel deaktiviert. Auf der Platte ändert sich
     *     nichts, die Datei wird nur nicht mehr eingelesen — der Cache
     *     liefert die Routen des abgeschalteten Plugins weiter aus.
     *   - Ein Plugin wird deinstalliert. Seine Datei ist weg, die mtime der
     *     übrigen bleibt.
     *   - Eine Plugin-Routendatei wird bearbeitet. Der alte Vergleich sah
     *     ausschließlich routes/, also nur die Kern-Dateien.
     *
     * Der vierte Fall, eine frische Installation, fiele auch einem
     * mtime-Vergleich auf. Über die Liste fallen alle vier auf.
     */
    private function invalidateStaleRouteCache(string $cacheFile): void
    {
        $stampFile = $cacheFile . '.sources';
        $current   = $this->routeSourceFingerprint();

        if (is_file($cacheFile) && @file_get_contents($stampFile) === $current) {
            return;
        }

        @unlink($cacheFile);

        $stampDir = dirname($stampFile);
        if (!is_dir($stampDir)) {
            @mkdir($stampDir, 0755, true);
        }
        @file_put_contents($stampFile, $current);
    }

    /**
     * Pfad und Änderungszeit jeder Quelldatei, sortiert. Sortiert, weil die
     * Ladereihenfolge der Plugins nichts über den Route-Satz aussagt und ein
     * Wechsel darin sonst grundlos den Cache verwürfe.
     */
    private function routeSourceFingerprint(): string
    {
        $parts = [];
        foreach ($this->routeSources as $file) {
            $parts[] = $file . ':' . (is_file($file) ? (int) filemtime($file) : 0);
        }
        sort($parts);

        return hash('xxh128', implode("\n", $parts));
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
                try {
                    $result = $handler($req, ...array_values($params));
                } catch (RedirectException $e) {
                    return $e->toResponse();
                }
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
                // Request als erster Parameter, dann die Route-Parameter.
                // Controller::redirect() wirft; hier wird daraus die Antwort,
                // bevor die Middlewares sie auf dem Rückweg sehen.
                try {
                    $result = $controller->{$method}($req, ...$args);
                } catch (RedirectException $e) {
                    return $e->toResponse();
                }
                return $result instanceof Response ? $result : Response::empty();
            };
        }

        throw new \InvalidArgumentException('Router: unsupported handler definition');
    }
}
