<?php

declare(strict_types=1);

namespace App\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;

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
                /** @var array{0:int,1:array{methods:array<int,string>,path:string,handler:mixed,middleware:array<int, string|Middleware\MiddlewareInterface>},2:array<string,string>} $info */
                $routeDef = $info[1];
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
        return simpleDispatcher(function (RouteCollector $rc): void {
            foreach ($this->routes as $idx => $route) {
                // FastRoute braucht eine serialisierbare Handler-Kennung —
                // wir geben den Array-Index, der unsere $routes indiziert,
                // plus die Original-Definition zum späteren Callable-Bau.
                $rc->addRoute($route['methods'], $route['path'], $route);
            }
        });
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
