<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\FabricaClient;
use App\Auth\FabricaIdentity;
use App\Http\Controllers\FabricaAuthController;
use App\Models\User;
use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use RuntimeException;
use Tests\IntegrationTestCase;

final class FabricaLoginTest extends IntegrationTestCase
{
    private const SUBJECT = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
    private const INSTANCE = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    /** @var array<string,mixed> */
    private array $environmentBefore;
    /** @var array<string,mixed> */
    private array $sessionBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->environmentBefore = $_ENV;
        $this->sessionBefore = $_SESSION ?? [];
        $_SESSION = [];
        $_ENV['AUTH_MODE'] = 'emergencyforge';
        $_ENV['FABRICA_URL'] = 'https://console.example.test';
        $_ENV['FABRICA_INSTANCE_ID'] = self::INSTANCE;
        $_ENV['FABRICA_INSTANCE_CREDENTIAL'] = self::INSTANCE . '.' . str_repeat('s', 43);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->environmentBefore;
        $_SESSION = $this->sessionBefore;
        parent::tearDown();
    }

    private function account(): User
    {
        return User::query()->create(['username' => 'Local member', 'discord_id' => (string) random_int(10000000, 99999999), 'full_admin' => false, 'role' => (int) \App\Models\Role::query()->where('default', 1)->value('id')]);
    }

    private function performCallback(): \EmergencyForge\Http\Response
    {
        $data = ['subject' => self::SUBJECT, 'instanceId' => self::INSTANCE, 'lease' => str_repeat('x', 43), 'expiresAt' => gmdate('c', time() + 3600), 'maxAge' => 60];
        $client = new FabricaClient($_ENV['FABRICA_URL'], self::INSTANCE, $_ENV['FABRICA_INSTANCE_CREDENTIAL'], new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR))]))]));
        $client->begin($_SESSION);
        return (new FabricaAuthController($client))->callback(new Request('GET', '/auth/fabrica/callback', ['code' => str_repeat('c', 43), 'state' => $_SESSION['fabrica_pending']['state']]));
    }

    public function test_unlinked_identity_cannot_create_a_user_or_become_first_admin(): void
    {
        $before = User::query()->count();
        self::assertSame(403, $this->performCallback()->status);
        self::assertFalse(SessionManager::isLoggedIn());
        self::assertSame($before, User::query()->count());
    }

    public function test_linked_identity_uses_existing_account_without_changing_roles(): void
    {
        $user = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, (int) $user->id);
        $response = $this->performCallback();
        self::assertSame(302, $response->status);
        self::assertSame((int) $user->id, SessionManager::userId());
        self::assertTrue(SessionManager::isLoggedIn());
        self::assertFalse((bool) $user->fresh()->full_admin);
        self::assertSame('Local member', $user->fresh()->username);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        SessionManager::logoutUser();
        self::assertArrayNotHasKey('fabrica_login', $_SESSION);
    }

    public function test_a_different_account_cannot_claim_an_existing_identity(): void
    {
        $first = $this->account(); $second = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, (int) $first->id);
        $this->expectException(RuntimeException::class);
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, (int) $second->id);
    }

    public function test_switching_to_central_mode_rejects_an_old_local_session(): void
    {
        SessionManager::loginUser($this->account()->toArray());
        self::assertFalse(SessionManager::isLoggedIn());
        self::assertNull(SessionManager::userId());
    }

    public function test_deleted_or_deactivated_local_account_cannot_log_in(): void
    {
        $user = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, (int) $user->id);
        $user->is_active = false; $user->save();
        self::assertSame(403, $this->performCallback()->status);
        self::assertFalse(SessionManager::isLoggedIn());
    }

    public function test_login_route_can_resolve_its_controller_and_start_the_handoff(): void
    {
        $controller = $GLOBALS['app_container']->get(FabricaAuthController::class);
        self::assertSame(302, $controller->login(new Request('GET', '/auth/fabrica'))->status);
    }
}
