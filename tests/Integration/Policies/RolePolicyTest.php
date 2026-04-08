<?php

declare(strict_types=1);

namespace Tests\Integration\Policies;

use App\Auth\Gate;
use App\Models\Role;
use App\Policies\RolePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class RolePolicyTest extends IntegrationTestCase
{
    private int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $role = new Role();
        $role->name        = 'PolicyRoleTest_' . uniqid();
        $role->priority    = 50;
        $role->permissions = [];
        $role->is_default  = false;
        $role->admin       = false;
        $role->save();
        $this->roleId = $role->id;
    }

    protected function tearDown(): void
    {
        Role::where('id', $this->roleId)->delete();
        unset($_SESSION['permissions'], $_SESSION['userid']);
        parent::tearDown();
    }

    #[Test]
    public function full_admin_can_create_roles(): void
    {
        $_SESSION['permissions'] = ['full_admin'];
        $this->assertTrue(RolePolicy::create());
        $this->assertTrue(Gate::allows('role.create'));
    }

    #[Test]
    public function admin_alone_cannot_create_roles(): void
    {
        $_SESSION['permissions'] = ['admin'];
        $this->assertFalse(RolePolicy::create());
        $this->assertFalse(Gate::allows('role.create'));
    }

    #[Test]
    public function full_admin_can_update_role(): void
    {
        $_SESSION['permissions'] = ['full_admin'];
        $role = Role::find($this->roleId);
        $this->assertTrue(RolePolicy::update($role));
        $this->assertTrue(Gate::allows('role.update', $role));
    }

    #[Test]
    public function full_admin_can_delete_role(): void
    {
        $_SESSION['permissions'] = ['full_admin'];
        $role = Role::find($this->roleId);
        $this->assertTrue(RolePolicy::delete($role));
        $this->assertTrue(Gate::allows('role.delete', $role));
    }

    #[Test]
    public function viewList_is_allowed_for_users_view(): void
    {
        $_SESSION['permissions'] = ['users.view'];
        $this->assertTrue(RolePolicy::viewList());

        $_SESSION['permissions'] = ['kb.view'];
        $this->assertFalse(RolePolicy::viewList());
    }
}
