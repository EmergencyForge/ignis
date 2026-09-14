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
use DomainException;
use App\Models\RegistrationCode;
use Tests\IntegrationTestCase;

final class FabricaLoginTest extends IntegrationTestCase
{
    private const SUBJECT = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
    private const OTHER_SUBJECT = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
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

    /** @param array<string,mixed> $profile */
    private function performCallback(array $profile = []): \EmergencyForge\Http\Response
    {
        $data = ['username' => 'Sync member', 'subject' => self::SUBJECT, 'instanceId' => self::INSTANCE, 'lease' => str_repeat('x', 43), 'expiresAt' => gmdate('c', time() + 3600), 'maxAge' => 60];
        $data = array_replace($data, $profile);
        $client = new FabricaClient($_ENV['FABRICA_URL'], self::INSTANCE, $_ENV['FABRICA_INSTANCE_CREDENTIAL'], new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR))]))]));
        $client->begin($_SESSION);
        return (new FabricaAuthController($client))->callback(new Request('GET', '/auth/fabrica/callback', ['code' => str_repeat('c', 43), 'state' => $_SESSION['fabrica_pending']['state']]));
    }

    public function test_open_registration_creates_a_normal_user_and_reuses_the_mapping(): void
    {
        $before = User::query()->count();
        self::assertSame(302, $this->performCallback()->status);
        self::assertTrue(SessionManager::isLoggedIn());
        self::assertSame($before + 1, User::query()->count());
        $user = User::query()->findOrFail(SessionManager::userId());
        self::assertFalse((bool) $user->full_admin);
        self::assertNull($user->discord_id);
        self::assertSame('Sync member', $user->username);
        self::assertSame($user->id, FabricaIdentity::user($_ENV['FABRICA_URL'], self::SUBJECT)?->id);
        self::assertSame(302, $this->performCallback()->status);
        self::assertSame($user->id, SessionManager::userId());
        self::assertSame($before + 1, User::query()->count());
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
        self::assertSame(302, $this->performCallback()->status);
        self::assertFalse(SessionManager::isLoggedIn());
    }

    public function test_invitation_creates_account_and_mapping_and_cannot_be_reused(): void
    {
        $code = RegistrationCode::query()->create(['code' => 'sync-invitation']);
        $user = FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, 'New member', 'code', $code->code);
        self::assertSame($user->id, $code->fresh()->used_by);
        self::assertNotNull($code->fresh()->used_at);
        self::assertSame($user->id, FabricaIdentity::user($_ENV['FABRICA_URL'], self::SUBJECT)?->id);
        self::assertNull($user->discord_id);
        self::assertFalse((bool) $user->full_admin);
        $before = User::query()->count();
        try {
            FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::OTHER_SUBJECT, 'Another member', 'code', $code->code);
            self::fail('Used invitation accepted');
        } catch (DomainException) {
            self::assertSame($before, User::query()->count());
            self::assertNull(FabricaIdentity::user($_ENV['FABRICA_URL'], self::OTHER_SUBJECT));
        }
    }

    public function test_missing_expired_and_invalid_invitations_create_nothing(): void
    {
        RegistrationCode::query()->create(['code' => 'expired-sync', 'expires_at' => date('Y-m-d H:i:s', time() - 60)]);
        $before = User::query()->count();
        foreach ([null, '', 'unknown-sync', 'expired-sync'] as $code) {
            try {
                FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, 'New member', 'code', $code);
                self::fail('Invalid invitation accepted');
            } catch (DomainException) {
                self::assertSame($before, User::query()->count());
                self::assertNull(FabricaIdentity::user($_ENV['FABRICA_URL'], self::SUBJECT));
            }
        }
    }

    public function test_closed_and_unknown_modes_refuse_new_users_even_with_an_invitation(): void
    {
        $code = RegistrationCode::query()->create(['code' => 'closed-sync']);
        $before = User::query()->count();
        foreach (['closed', 'invalid'] as $mode) {
            try {
                FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, 'New member', $mode, $code->code);
                self::fail('Closed registration accepted');
            } catch (DomainException) {
                self::assertSame($before, User::query()->count());
                self::assertNull($code->fresh()->used_at);
            }
        }
    }

    public function test_existing_account_can_sign_in_in_every_mode_without_consuming_an_invitation(): void
    {
        $user = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, $user->id);
        $code = RegistrationCode::query()->create(['code' => 'existing-sync']);
        foreach (['open', 'code', 'closed'] as $mode) {
            $resolved = FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, null, $mode, $code->code);
            self::assertSame($user->id, $resolved->id);
            self::assertSame('Local member', $resolved->username);
            self::assertNull($code->fresh()->used_at);
        }
    }

    public function test_disabled_account_is_never_recreated_in_any_mode(): void
    {
        $user = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, $user->id);
        $user->is_active = false; $user->save();
        $before = User::query()->count();
        $code = RegistrationCode::query()->create(['code' => 'disabled-sync']);
        foreach (['open', 'code', 'closed'] as $mode) {
            try {
                FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, 'New member', $mode, $code->code);
                self::fail('Disabled account admitted');
            } catch (DomainException $e) {
                self::assertStringContainsString('deaktiviert', $e->getMessage());
                self::assertSame($before, User::query()->count());
                self::assertNull($code->fresh()->used_at);
            }
        }
    }

    public function test_a_matching_name_does_not_take_over_an_existing_discord_account(): void
    {
        $existing = $this->account();
        $user = FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, $existing->username, 'open', null);
        self::assertNotSame($existing->id, $user->id);
        self::assertNull($user->discord_id);
        self::assertSame($existing->discord_id, $existing->fresh()->discord_id);
    }

    public function test_failed_account_creation_rolls_back_the_invitation(): void
    {
        $code = RegistrationCode::query()->create(['code' => 'rollback-sync']);
        $before = User::query()->count();
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(new \Illuminate\Events\Dispatcher());
        User::created(static function (): void { throw new RuntimeException('Injected write failure'); });
        try {
            FabricaIdentity::resolve($_ENV['FABRICA_URL'], self::SUBJECT, 'New member', 'code', $code->code);
            self::fail('Injected failure ignored');
        } catch (RuntimeException $e) {
            self::assertSame('Injected write failure', $e->getMessage());
            self::assertSame($before, User::query()->count());
            self::assertNull($code->fresh()->used_at);
            self::assertNull(FabricaIdentity::user($_ENV['FABRICA_URL'], self::SUBJECT));
        } finally {
            if ($dispatcher !== null) User::setEventDispatcher($dispatcher);
            else User::unsetEventDispatcher();
        }
    }

    public function test_old_fabrica_response_allows_linked_accounts_but_cannot_register_new_ones(): void
    {
        $before = User::query()->count();
        $response = $this->performCallback(['username' => null]);
        self::assertSame(302, $response->status);
        self::assertFalse(SessionManager::isLoggedIn());
        self::assertSame($before, User::query()->count());
        $user = $this->account();
        FabricaIdentity::link($_ENV['FABRICA_URL'], self::SUBJECT, $user->id);
        self::assertSame(302, $this->performCallback(['username' => null])->status);
        self::assertSame($user->id, SessionManager::userId());
    }

    public function test_sync_user_without_discord_cannot_claim_documents_without_an_issuer(): void
    {
        self::assertSame(302, $this->performCallback()->status);
        SessionManager::setPermissions([]);
        foreach ([null, '', 'another-discord-user'] as $issuer) {
            $docId = random_int(1000000, 9999999);
            \Illuminate\Database\Capsule\Manager::table('intra_mitarbeiter_dokumente')->insert(['docid' => $docId, 'ausstellerid' => $issuer]);
            $response = (new \App\Http\Controllers\Api\PersonnelDocumentController())->getDocument(new Request('GET', '/api/documents/get-document', ['docid' => (string) $docId]));
            self::assertSame(403, $response->status);
        }
    }

    public function test_login_route_can_resolve_its_controller_and_start_the_handoff(): void
    {
        $controller = $GLOBALS['app_container']->get(FabricaAuthController::class);
        self::assertSame(302, $controller->login(new Request('GET', '/auth/fabrica'))->status);
    }
}
