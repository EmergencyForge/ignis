<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class UserModelTest extends IntegrationTestCase
{
    private int $roleId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        // Saubere Test-Daten anlegen — jede Test-Methode bekommt eigene Records
        $role = new Role();
        $role->name        = 'TestRole_' . uniqid();
        $role->priority    = 99;
        $role->permissions = ['test.read', 'test.write'];
        $role->is_default  = false;
        $role->admin       = false;
        $role->save();
        $this->roleId = $role->id;

        $user = new User();
        $user->username   = 'testuser_' . uniqid();
        $user->fullname   = 'Test User';
        $user->discord_id = '123456789012345678';
        $user->role       = $this->roleId;
        $user->full_admin = false;
        $user->is_active  = true;
        $user->save();
        $this->userId = $user->id;
    }

    protected function tearDown(): void
    {
        User::where('id', $this->userId)->delete();
        Role::where('id', $this->roleId)->delete();
        parent::tearDown();
    }

    #[Test]
    public function user_can_be_persisted_and_retrieved(): void
    {
        $user = User::find($this->userId);
        $this->assertNotNull($user);
        $this->assertSame('Test User', $user->fullname);
        $this->assertSame('123456789012345678', $user->discord_id);
    }

    #[Test]
    public function user_casts_are_applied(): void
    {
        $user = User::find($this->userId);
        $this->assertIsBool($user->is_active);
        $this->assertIsBool($user->full_admin);
        $this->assertIsInt($user->id);
        $this->assertIsInt($user->role);
    }

    #[Test]
    public function user_belongs_to_role(): void
    {
        $user = User::with('userRole')->find($this->userId);
        $this->assertInstanceOf(Role::class, $user->userRole);
        $this->assertSame($this->roleId, $user->userRole->id);
    }

    #[Test]
    public function active_scope_filters_correctly(): void
    {
        $found = User::active()->where('id', $this->userId)->first();
        $this->assertNotNull($found);

        $foundInactive = User::inactive()->where('id', $this->userId)->first();
        $this->assertNull($foundInactive);
    }

    #[Test]
    public function user_role_is_loaded_eagerly(): void
    {
        $user = User::with('userRole')->find($this->userId);
        $relations = $user->getRelations();
        $this->assertArrayHasKey('userRole', $relations);
    }
}
