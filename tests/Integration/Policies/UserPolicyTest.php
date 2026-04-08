<?php

declare(strict_types=1);

namespace Tests\Integration\Policies;

use App\Auth\Gate;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Integration-Tests für UserPolicy. Nutzt die Test-DB um echte User+Role
 * Beziehungen aufzubauen, weil die Policy-Methoden Priority-Vergleiche und
 * Relationship-Lookups machen.
 */
class UserPolicyTest extends IntegrationTestCase
{
    private int $highRoleId;     // hohe Berechtigung (niedrige Priority-Zahl = 10)
    private int $lowRoleId;      // niedrige Berechtigung (hohe Priority-Zahl = 100)
    private int $actorUserId;    // unser Test-Aktor
    private int $weakerUserId;   // schwächerer User (niedrigere Berechtigung)
    private int $adminUserId;    // full_admin User

    protected function setUp(): void
    {
        parent::setUp();

        $highRole = new Role();
        $highRole->name        = 'PolicyHigh_' . uniqid();
        $highRole->priority    = 10;
        $highRole->permissions = ['admin'];
        $highRole->is_default  = false;
        $highRole->admin       = false;
        $highRole->save();
        $this->highRoleId = $highRole->id;

        $lowRole = new Role();
        $lowRole->name        = 'PolicyLow_' . uniqid();
        $lowRole->priority    = 100;
        $lowRole->permissions = [];
        $lowRole->is_default  = false;
        $lowRole->admin       = false;
        $lowRole->save();
        $this->lowRoleId = $lowRole->id;

        $actor = new User();
        $actor->username   = 'actor_' . uniqid();
        $actor->discord_id = (string) random_int(100000000000000000, 999999999999999999);
        $actor->role       = $this->highRoleId;
        $actor->full_admin = false;
        $actor->is_active  = true;
        $actor->save();
        $this->actorUserId = $actor->id;

        $weaker = new User();
        $weaker->username   = 'weaker_' . uniqid();
        $weaker->discord_id = (string) random_int(100000000000000000, 999999999999999999);
        $weaker->role       = $this->lowRoleId;
        $weaker->full_admin = false;
        $weaker->is_active  = true;
        $weaker->save();
        $this->weakerUserId = $weaker->id;

        $admin = new User();
        $admin->username   = 'admin_' . uniqid();
        $admin->discord_id = (string) random_int(100000000000000000, 999999999999999999);
        $admin->role       = $this->lowRoleId;
        $admin->full_admin = true;
        $admin->is_active  = true;
        $admin->save();
        $this->adminUserId = $admin->id;

        // Aktor in der Session simulieren — Permissions = admin (alles erlaubt
        // bis auf full_admin) + role_priority = 10
        $_SESSION['userid']        = $this->actorUserId;
        $_SESSION['permissions']   = ['admin'];
        $_SESSION['role_priority'] = 10;
    }

    protected function tearDown(): void
    {
        User::whereIn('id', [$this->actorUserId, $this->weakerUserId, $this->adminUserId])->delete();
        Role::whereIn('id', [$this->highRoleId, $this->lowRoleId])->delete();
        unset($_SESSION['userid'], $_SESSION['permissions'], $_SESSION['role_priority']);
        parent::tearDown();
    }

    #[Test]
    public function actor_can_view_user_list(): void
    {
        $this->assertTrue(UserPolicy::viewList());
    }

    #[Test]
    public function actor_cannot_view_list_without_permission(): void
    {
        $_SESSION['permissions'] = ['kb.view'];
        $this->assertFalse(UserPolicy::viewList());
    }

    #[Test]
    public function actor_can_update_weaker_user(): void
    {
        $weaker = User::with('userRole')->find($this->weakerUserId);
        $this->assertTrue(UserPolicy::update($weaker));
    }

    #[Test]
    public function actor_cannot_update_self(): void
    {
        $self = User::with('userRole')->find($this->actorUserId);
        $this->assertFalse(UserPolicy::update($self));
    }

    #[Test]
    public function actor_cannot_update_full_admin(): void
    {
        $admin = User::with('userRole')->find($this->adminUserId);
        $this->assertFalse(UserPolicy::update($admin));
    }

    #[Test]
    public function actor_cannot_update_user_with_higher_or_equal_priority(): void
    {
        // Aktor selbst hat priority 10. Wir bauen einen anderen User mit
        // gleicher Rolle (also gleicher priority).
        $peer = new User();
        $peer->username   = 'peer_' . uniqid();
        $peer->discord_id = (string) random_int(100000000000000000, 999999999999999999);
        $peer->role       = $this->highRoleId;
        $peer->full_admin = false;
        $peer->is_active  = true;
        $peer->save();

        try {
            $peer = User::with('userRole')->find($peer->id);
            $this->assertFalse(UserPolicy::update($peer));
        } finally {
            User::where('id', $peer->id)->delete();
        }
    }

    #[Test]
    public function gate_routes_user_update_to_user_policy(): void
    {
        $weaker = User::with('userRole')->find($this->weakerUserId);
        $this->assertTrue(Gate::allows('user.update', $weaker));
    }

    #[Test]
    public function gate_denies_user_update_for_self(): void
    {
        $self = User::with('userRole')->find($this->actorUserId);
        $this->assertTrue(Gate::denies('user.update', $self));
    }

    #[Test]
    public function delete_uses_users_delete_permission(): void
    {
        $weaker = User::with('userRole')->find($this->weakerUserId);

        $_SESSION['permissions'] = ['users.delete'];
        $this->assertTrue(UserPolicy::delete($weaker));

        $_SESSION['permissions'] = ['users.edit'];
        $this->assertFalse(UserPolicy::delete($weaker));
    }
}
