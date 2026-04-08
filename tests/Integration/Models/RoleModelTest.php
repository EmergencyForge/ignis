<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class RoleModelTest extends IntegrationTestCase
{
    private int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $role = new Role();
        $role->name        = 'PermTest_' . uniqid();
        $role->priority    = 50;
        $role->permissions = ['users.view', 'users.edit', 'enotf.create'];
        $role->is_default  = false;
        $role->admin       = false;
        $role->save();
        $this->roleId = $role->id;
    }

    protected function tearDown(): void
    {
        Role::where('id', $this->roleId)->delete();
        parent::tearDown();
    }

    #[Test]
    public function permissions_are_cast_to_array(): void
    {
        $role = Role::find($this->roleId);
        $this->assertIsArray($role->permissions);
        $this->assertCount(3, $role->permissions);
        $this->assertContains('users.view', $role->permissions);
    }

    #[Test]
    public function has_permission_returns_true_for_existing_permission(): void
    {
        $role = Role::find($this->roleId);
        $this->assertTrue($role->hasPermission('users.view'));
        $this->assertTrue($role->hasPermission('enotf.create'));
    }

    #[Test]
    public function has_permission_returns_false_for_missing_permission(): void
    {
        $role = Role::find($this->roleId);
        $this->assertFalse($role->hasPermission('admin.everything'));
    }

    #[Test]
    public function admin_role_has_all_permissions(): void
    {
        $admin = new Role();
        $admin->name        = 'AdminTest_' . uniqid();
        $admin->priority    = 1;
        $admin->permissions = [];
        $admin->is_default  = false;
        $admin->admin       = true;
        $admin->save();

        try {
            $this->assertTrue($admin->hasPermission('any.random.permission'));
        } finally {
            $admin->delete();
        }
    }

    #[Test]
    public function is_default_accessor_maps_to_default_column(): void
    {
        $role = Role::find($this->roleId);
        $this->assertFalse($role->is_default);

        $role->is_default = true;
        $role->save();

        $reloaded = Role::find($this->roleId);
        $this->assertTrue($reloaded->is_default);
    }

    #[Test]
    public function has_many_users_relationship(): void
    {
        $user = new User();
        $user->username   = 'reltest_' . uniqid();
        $user->discord_id = '999999999999999999';
        $user->role       = $this->roleId;
        $user->full_admin = false;
        $user->is_active  = true;
        $user->save();

        try {
            $role = Role::with('users')->find($this->roleId);
            $this->assertCount(1, $role->users);
            $this->assertSame($user->id, $role->users->first()->id);
        } finally {
            $user->delete();
        }
    }
}
