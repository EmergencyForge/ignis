<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\RouterFactory;
use EmergencyForge\Http\Pipeline;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Http\Csrf as EnotfCsrf;
use Plugin\EnotfV2\Http\CsrfMiddleware as EnotfCsrfMiddleware;
use ReflectionClass;
use Tests\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $sessionBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionBefore = $_SESSION ?? [];
        $_SESSION = ['csrf_token' => 'core-token', EnotfCsrf::SESSION_KEY => 'plugin-token'];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBefore;
        parent::tearDown();
    }

    #[Test]
    public function machine_exceptions_use_api_key_authentication_on_the_registered_routes(): void
    {
        if (!defined('API_KEY')) {
            define('API_KEY', 'csrf-machine-test-key');
        }
        $_SESSION = [];
        $pipeline = new Pipeline($this->container);
        $router = RouterFactory::create($this->container, $pipeline, cache: false);
        $root = dirname(__DIR__, 4);
        require $root . '/routes/api.php';
        require $root . '/routes/api.session.php';
        require $root . '/plugins/firetab/routes.api.php';

        $routes = (new ReflectionClass($router))->getProperty('routes')->getValue($router);
        $exempt = (new ReflectionClass(CsrfMiddleware::class))->getConstant('EXEMPT');

        foreach ($exempt as $path) {
            $matching = array_values(array_filter($routes, static fn (array $route): bool
                => $route['path'] === $path && in_array('POST', $route['methods'], true)));
            $this->assertCount(1, $matching, $path);
            $stack = $matching[0]['middleware'];
            $this->assertContains(ApiKeyMiddleware::class, $stack, $path);

            foreach ([null, 'wrong-key', (string) API_KEY] as $key) {
                $server = ['REMOTE_ADDR' => '192.0.2.1'];
                if ($key !== null) {
                    $server['HTTP_X_API_KEY'] = $key;
                }
                $response = $pipeline->run(
                    new Request('POST', $path, server: $server),
                    [new CsrfMiddleware(), ...$stack],
                    static fn (Request $request): Response => Response::text((string) $request->attribute('api_auth')),
                );
                $this->assertSame($key === API_KEY ? 200 : 403, $response->status, $path);
                if ($key === API_KEY) {
                    $this->assertSame('key', $response->body, $path);
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,string> $server
     */
    #[Test]
    #[DataProvider('tokenRequests')]
    public function session_tokens_are_checked_in_their_own_context(
        string $path,
        array $post,
        array $server,
        int $expectedStatus,
    ): void {
        $stack = [new CsrfMiddleware()];
        if (str_starts_with($path, '/enotf-v2/')) {
            $stack[] = new EnotfCsrfMiddleware();
        }
        $response = (new Pipeline($this->container))->run(
            new Request('POST', $path, post: $post, server: $server),
            $stack,
            static fn (): Response => Response::text('reached'),
        );

        $this->assertSame($expectedStatus, $response->status);
    }

    /** @return array<string,array{string,array<string,mixed>,array<string,string>,int}> */
    public static function tokenRequests(): array
    {
        return [
            'crew login form' => ['/enotf-v2/login', ['_csrf' => 'plugin-token'], [], 200],
            'crew create form' => ['/enotf-v2/create', ['_csrf' => 'plugin-token'], [], 200],
            'crew lockscreen form' => ['/enotf-v2/lockscreen', ['_csrf' => 'plugin-token'], [], 200],
            'QM header' => ['/enotf-v2/qm', [], ['HTTP_X_CSRF_TOKEN' => 'plugin-token'], 200],
            'autosave wrapper' => ['/api/enotf-v2/save-fields', [], ['HTTP_X_CSRF_TOKEN' => 'core-token'], 200],
            'plugin API token' => ['/api/enotf-v2/save-fields', [], ['HTTP_X_CSRF_TOKEN' => 'plugin-token'], 200],
            'core token' => ['/users/roles/create', ['csrf_token' => 'core-token'], [], 200],
            'missing crew token' => ['/enotf-v2/login', [], [], 403],
            'wrong crew token' => ['/enotf-v2/login', ['_csrf' => 'wrong-token'], [], 403],
            'malformed crew token' => ['/enotf-v2/login', ['_csrf' => []], [], 403],
            'missing API token' => ['/api/enotf-v2/save-fields', [], [], 403],
            'same-origin alone is insufficient' => ['/enotf-v2/login', [], ['HTTP_ORIGIN' => 'https://example.test', 'HTTP_HOST' => 'example.test'], 403],
            'plugin token cannot write core' => ['/users/roles/create', [], ['HTTP_X_CSRF_TOKEN' => 'plugin-token'], 403],
            'similar API prefix' => ['/api/enotf-v20/save-fields', [], ['HTTP_X_CSRF_TOKEN' => 'plugin-token'], 403],
            'similar web prefix' => ['/enotf-v2-other/login', ['_csrf' => 'plugin-token'], [], 403],
            'federation is not an API-key exception' => ['/api/federation/pair', [], ['HTTP_X_API_KEY' => 'some-key'], 403],
        ];
    }

    #[Test]
    public function plugin_token_without_a_matching_session_is_rejected(): void
    {
        unset($_SESSION[EnotfCsrf::SESSION_KEY]);
        $response = (new CsrfMiddleware())->process(
            new Request('POST', '/enotf-v2/login', post: ['_csrf' => 'plugin-token']),
            static fn (): Response => Response::text('reached'),
        );

        $this->assertSame(403, $response->status);
    }
}
