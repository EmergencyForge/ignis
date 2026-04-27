<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Policies\ManvPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MANV-Modul ist ein "alle oder keiner"-Modul: jede Policy-Methode
 * verlangt aktuell `['admin', 'manv.manage']`. Tests fixieren das,
 * damit eine spätere Differenzierung (z.B. read-only Beobachter)
 * bewusst alle Policy-Methoden anfasst statt einzelne stillschweigend
 * zu entkoppeln.
 */
class ManvPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    private function loginWith(array $permissions): void
    {
        $_SESSION['userid']      = 42;
        $_SESSION['permissions'] = $permissions;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function abilityProvider(): array
    {
        return [
            'viewList' => ['viewList'],
            'view'     => ['view'],
            'create'   => ['create'],
            'update'   => ['update'],
            'delete'   => ['delete'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('abilityProvider')]
    public function ability_rejects_when_no_permission(string $ability): void
    {
        $this->loginWith(['user']);
        $this->assertFalse(ManvPolicy::$ability());
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('abilityProvider')]
    public function ability_passes_with_admin_permission(string $ability): void
    {
        $this->loginWith(['admin']);
        $this->assertTrue(ManvPolicy::$ability());
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('abilityProvider')]
    public function ability_passes_with_manv_manage_permission(string $ability): void
    {
        $this->loginWith(['manv.manage']);
        $this->assertTrue(ManvPolicy::$ability());
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('abilityProvider')]
    public function ability_rejects_anonymous(string $ability): void
    {
        $this->assertFalse(ManvPolicy::$ability());
    }

    #[Test]
    public function unrelated_permissions_dont_grant_access(): void
    {
        // Ähnlich klingende Permissions („manv.view") dürfen NICHT als
        // Substring-Match durchrutschen — Permissions::check matched exakt.
        $this->loginWith(['manv.view', 'manv.observer']);

        $this->assertFalse(ManvPolicy::viewList());
        $this->assertFalse(ManvPolicy::create());
        $this->assertFalse(ManvPolicy::update());
        $this->assertFalse(ManvPolicy::delete());
    }
}
