<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Tests\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $pipeline = new Pipeline($this->container);
        // enableCache=false: Test-Router-Instanzen sollen keinen File-Cache
        // teilen — sonst würde die Live-Cache-Datei (Produktions-Routen) die
        // Test-URL-Patterns überschreiben und alle Tests 404en.
        $this->router = new Router($this->container, $pipeline, enableCache: false);
    }

    private function request(string $method, string $path): Request
    {
        return new Request($method, $path);
    }

    #[Test]
    public function dispatches_get_route_with_closure(): void
    {
        $this->router->get('/ping', fn ($req) => Response::json(['pong' => true]));

        $res = $this->router->dispatch($this->request('GET', '/ping'));

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('"pong":true', $res->body);
    }

    #[Test]
    public function returns_404_for_unknown_path(): void
    {
        $res = $this->router->dispatch($this->request('GET', '/does-not-exist'));
        $this->assertSame(404, $res->status);
    }

    #[Test]
    public function returns_405_with_allow_header_for_wrong_method(): void
    {
        $this->router->get('/only-get', fn () => Response::text('ok'));

        $res = $this->router->dispatch($this->request('POST', '/only-get'));

        $this->assertSame(405, $res->status);
        $this->assertSame('GET', $res->headers['Allow'] ?? '');
    }

    #[Test]
    public function extracts_route_parameters_into_handler_args(): void
    {
        $this->router->get('/users/{id:\d+}', function (Request $req, string $id) {
            return Response::json(['id' => $id, 'attr' => $req->attribute('id')]);
        });

        $res = $this->router->dispatch($this->request('GET', '/users/42'));

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('"id":"42"', $res->body);
        $this->assertStringContainsString('"attr":"42"', $res->body);
    }

    #[Test]
    public function middleware_runs_before_handler_and_can_short_circuit(): void
    {
        $blocker = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                return Response::text('blocked', 418);
            }
        };

        $this->router->get('/guarded', fn () => Response::text('should not run'), [$blocker]);

        $res = $this->router->dispatch($this->request('GET', '/guarded'));

        $this->assertSame(418, $res->status);
        $this->assertSame('blocked', $res->body);
    }

    #[Test]
    public function middleware_order_is_outside_in(): void
    {
        $log = [];

        $outer = new class ($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'outer:before';
                $res = $next($request);
                $this->log[] = 'outer:after';
                return $res;
            }
        };
        $inner = new class ($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'inner:before';
                $res = $next($request);
                $this->log[] = 'inner:after';
                return $res;
            }
        };

        $this->router->get('/ordered', function () use (&$log) {
            $log[] = 'handler';
            return Response::text('ok');
        }, [$outer, $inner]);

        $this->router->dispatch($this->request('GET', '/ordered'));

        $this->assertSame(
            ['outer:before', 'inner:before', 'handler', 'inner:after', 'outer:after'],
            $log
        );
    }

    #[Test]
    public function group_applies_prefix_and_shared_middleware(): void
    {
        $tag = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                $res = $next($request->withAttribute('tagged', true));
                return $res->withHeader('X-Group-Tag', 'yes');
            }
        };

        $this->router->group('/admin', [$tag], function ($r) {
            $r->get('/foo', fn (Request $req) => Response::json(['tagged' => $req->attribute('tagged')]));
        });

        $res = $this->router->dispatch($this->request('GET', '/admin/foo'));

        $this->assertSame(200, $res->status);
        $this->assertSame('yes', $res->headers['X-Group-Tag'] ?? '');
        $this->assertStringContainsString('"tagged":true', $res->body);
    }

    #[Test]
    public function controller_handler_is_resolved_from_container(): void
    {
        $this->router->get('/hello', [RouterTestDummyController::class, 'hello']);

        $res = $this->router->dispatch($this->request('GET', '/hello'));

        $this->assertSame(200, $res->status);
        $this->assertSame('hello from controller', $res->body);
    }
}

class RouterTestDummyController
{
    public function hello(Request $request): Response
    {
        return Response::text('hello from controller');
    }
}
