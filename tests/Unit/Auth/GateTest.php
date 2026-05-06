<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Gate;
use App\Exceptions\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Gate-Resolution-Logik. Diese Tests prüfen NICHT die echte
 * Permission-Logik (das machen die Policy-Tests gegen die Test-DB), sondern
 * nur dass Gate::allows() die richtigen Policy-Methoden findet und richtig
 * mit Edge-Cases umgeht.
 */
class GateTest extends TestCase
{
    #[Test]
    public function unknown_resource_returns_false_safely(): void
    {
        $this->assertFalse(Gate::allows('nonexistent.update'));
        $this->assertTrue(Gate::denies('nonexistent.update'));
    }

    #[Test]
    public function unknown_action_on_known_resource_returns_false(): void
    {
        $this->assertFalse(Gate::allows('user.flyToTheMoon'));
    }

    #[Test]
    public function ability_without_dot_returns_false(): void
    {
        $this->assertFalse(Gate::allows('admin'));
        $this->assertFalse(Gate::allows(''));
    }

    #[Test]
    public function authorize_throws_on_denial(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(403);
        Gate::authorize('nonexistent.update');
    }

    #[Test]
    public function authorize_does_not_throw_for_known_passing_ability(): void
    {
        // Setup: Session mit allen Permissions als Admin
        $_SESSION['permissions']  = ['admin'];
        $_SESSION['userid']       = 1;
        $_SESSION['role_priority'] = 1;

        try {
            Gate::authorize('user.viewList');
            $this->assertTrue(true); // didn't throw
        } finally {
            unset($_SESSION['permissions'], $_SESSION['userid'], $_SESSION['role_priority']);
        }
    }

    #[Test]
    public function authorization_exception_carries_ability(): void
    {
        try {
            Gate::authorize('foo.bar');
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame('foo.bar', $e->ability());
            $this->assertStringContainsString('foo.bar', $e->getMessage());
        }
    }

    #[Test]
    public function denies_is_inverse_of_allows(): void
    {
        $this->assertSame(
            !Gate::allows('user.viewList'),
            Gate::denies('user.viewList')
        );
    }
}
