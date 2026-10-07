<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Permissions;
use App\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Abgleich der Anmeldung mit Konto- und Rollenzeile bei jedem Request
 * (Permissions::applyToSession, ohne DB).
 */
final class PermissionsSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        SessionManager::loginUser(
            ['id' => 42, 'username' => 'alice', 'aktenid' => 7, 'role' => 3, 'discord_id' => '111'],
            ['users.view'],
        );
        SessionManager::setRoleDetails(3, 'Wachleitung', 'primary', 10);
        $_SESSION['fahrername'] = 'Müller';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /** @param array<string, mixed> $fields */
    private static function user(array $fields = []): object
    {
        return (object) array_replace(
            ['role' => 3, 'full_admin' => 0, 'is_active' => 1, 'username' => 'alice', 'discord_id' => '111'],
            $fields,
        );
    }

    /** @param list<string> $permissions */
    private static function role(array $permissions): object
    {
        return (object) ['permissions' => json_encode($permissions), 'name' => 'Wachleitung', 'color' => 'primary', 'priority' => 10];
    }

    #[Test]
    public function geloeschtes_konto_wird_abgemeldet(): void
    {
        Permissions::applyToSession(null, null);

        $this->assertFalse(SessionManager::isLoggedIn());
        $this->assertArrayNotHasKey('permissions', $_SESSION);
        $this->assertArrayNotHasKey('role_name', $_SESSION);
        // Die eNOTF-Crew ist eine eigene Anmeldung und bleibt.
        $this->assertSame('Müller', $_SESSION['fahrername']);
    }

    #[Test]
    public function deaktiviertes_konto_wird_abgemeldet(): void
    {
        Permissions::applyToSession(self::user(['is_active' => 0]), self::role(['users.view']));

        $this->assertFalse(SessionManager::isLoggedIn());
        $this->assertSame([], SessionManager::permissions());
    }

    #[Test]
    public function geaenderte_rolle_gilt_sofort(): void
    {
        Permissions::applyToSession(self::user(), self::role(['users.view', 'users.edit']));

        $this->assertTrue(SessionManager::isLoggedIn());
        $this->assertSame(['users.view', 'users.edit'], SessionManager::permissions());
        $this->assertSame(10, $_SESSION['role_priority']);
    }

    #[Test]
    public function ohne_rolle_bleiben_keine_alten_rollendaten_stehen(): void
    {
        Permissions::applyToSession(self::user(['role' => null]), null);

        $this->assertTrue(SessionManager::isLoggedIn());
        $this->assertSame([], SessionManager::permissions());
        $this->assertNull($_SESSION['role_id']);
        $this->assertNull($_SESSION['role_name']);
        $this->assertNull($_SESSION['role_priority']);
    }

    #[Test]
    public function full_admin_bekommt_admin_plus(): void
    {
        Permissions::applyToSession(self::user(['full_admin' => 1]), null);

        $this->assertSame(['full_admin'], SessionManager::permissions());
        $this->assertSame('Admin+', $_SESSION['role_name']);
    }

    #[Test]
    public function name_und_discord_id_kommen_aus_der_db(): void
    {
        Permissions::applyToSession(self::user(['username' => 'alice2', 'discord_id' => '222']), self::role([]));

        $this->assertSame('alice2', SessionManager::username());
        $this->assertSame('222', $_SESSION['discordtag']);
    }
}
