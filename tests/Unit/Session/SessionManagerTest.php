<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use App\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression-Coverage für die fünf parallelen Auth-Kontexte:
 *   1. Standard (Discord-OAuth / Login-Form)
 *   2. eNOTF-Crew
 *   3. Einsatz / FireTab
 *   4. FiveM-Character
 *   5. Klinikcode
 *
 * Jeder Kontext hat eine eigene Login-Methode auf SessionManager und
 * setzt einen disjunkten Set von Session-Keys. Der Test verifiziert,
 * dass jeweils nur die eigenen Keys gesetzt werden und ein Logout
 * andere Kontexte unberührt lässt — Cross-Contamination zwischen den
 * Kontexten war historisch ein Minenfeld.
 */
final class SessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function login_user_setzt_alle_standard_auth_keys(): void
    {
        SessionManager::loginUser([
            'id'         => 42,
            'username'   => 'alice',
            'aktenid'    => 7,
            'role'       => 3,
            'discord_id' => '1234567890',
        ], ['personnel.edit', 'admin']);

        $this->assertSame(42, $_SESSION['userid']);
        $this->assertSame('alice', $_SESSION['cirs_username']);
        $this->assertSame(7, $_SESSION['aktenid']);
        $this->assertSame(3, $_SESSION['role']);
        $this->assertSame('1234567890', $_SESSION['discordtag']);
        $this->assertSame(['personnel.edit', 'admin'], $_SESSION['permissions']);
        $this->assertTrue((bool) $_SESSION['permissions_loaded']);
    }

    #[Test]
    public function reader_helper_geben_korrekte_werte(): void
    {
        SessionManager::loginUser(
            ['id' => 99, 'username' => 'bob', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'abc'],
            ['mod'],
        );

        $this->assertTrue(SessionManager::isLoggedIn());
        $this->assertSame(99, SessionManager::userId());
        $this->assertSame('bob', SessionManager::username());
        $this->assertSame(['mod'], SessionManager::permissions());
    }

    #[Test]
    public function set_role_details_aktualisiert_die_role_keys(): void
    {
        SessionManager::setRoleDetails(5, 'Admin', 'danger', 0);

        $this->assertSame(5,        $_SESSION['role_id']);
        $this->assertSame('Admin',  $_SESSION['role_name']);
        $this->assertSame('danger', $_SESSION['role_color']);
        $this->assertSame(0,        $_SESSION['role_priority']);
    }

    #[Test]
    public function login_enotf_crew_setzt_alle_crew_keys(): void
    {
        SessionManager::loginEnotfCrew(
            'fahrer',
            'sess-token-xyz',
            [
                'fahrer'     => ['name' => 'Müller',   'quali' => 'NotSan'],
                'beifahrer'  => ['name' => 'Schmidt',  'quali' => 'RettSan'],
                'praktikant' => ['name' => 'Becker',   'quali' => 'PA'],
            ],
            'rtw_01',
        );

        $this->assertSame('fahrer',         $_SESSION['enotf_position']);
        $this->assertSame('sess-token-xyz', $_SESSION['enotf_session_token']);
        $this->assertSame('Müller',         $_SESSION['fahrername']);
        $this->assertSame('NotSan',         $_SESSION['fahrerquali']);
        $this->assertSame('Schmidt',        $_SESSION['beifahrername']);
        $this->assertSame('RettSan',        $_SESSION['beifahrerquali']);
        $this->assertSame('Becker',         $_SESSION['praktikantname']);
        $this->assertSame('PA',             $_SESSION['praktikantquali']);
        $this->assertSame('rtw_01',         $_SESSION['protfzg']);
        $this->assertTrue(SessionManager::isEnotfActive());
    }

    #[Test]
    public function login_einsatz_setzt_vehicle_und_operator(): void
    {
        SessionManager::loginEinsatz(7, 'LF 10/1', 42, 'Hauptbrandmeister Müller');

        $this->assertSame(7, $_SESSION['einsatz_vehicle_id']);
        $this->assertSame('LF 10/1', $_SESSION['einsatz_vehicle_name']);
        $this->assertSame(42, $_SESSION['einsatz_operator_id']);
        $this->assertSame('Hauptbrandmeister Müller', $_SESSION['einsatz_operator_name']);
        $this->assertTrue(SessionManager::isEinsatzActive());
    }

    #[Test]
    public function login_character_setzt_fivem_keys(): void
    {
        SessionManager::loginCharacter('char-uuid-1', 'fire', 'John Doe');

        $this->assertSame('char-uuid-1', $_SESSION['char_id']);
        $this->assertSame('fire',        $_SESSION['char_job']);
        $this->assertSame('John Doe',    $_SESSION['char_name']);
    }

    #[Test]
    public function login_klinikcode_setzt_zugang_und_zeitstempel(): void
    {
        $before = time();
        SessionManager::loginKlinikcode('ENR-2024-001');
        $after = time();

        $this->assertSame('ENR-2024-001', $_SESSION['klinik_access_enr']);
        $this->assertGreaterThanOrEqual($before, $_SESSION['klinik_access_time']);
        $this->assertLessThanOrEqual($after, $_SESSION['klinik_access_time']);
    }

    #[Test]
    public function logout_user_clearet_nur_standard_keys(): void
    {
        // Standard + eNOTF parallel aktiv (typisches Setup: Crew-Member im
        // RTW ist auch als User im Backoffice eingeloggt).
        SessionManager::loginUser(
            ['id' => 1, 'username' => 'a', 'aktenid' => null, 'role' => 1, 'discord_id' => 'x'],
            [],
        );
        SessionManager::loginEnotfCrew('fahrer', 't', [
            'fahrer' => ['name' => 'A', 'quali' => 'NS'],
        ]);

        SessionManager::logoutUser();

        $this->assertArrayNotHasKey('userid',        $_SESSION);
        $this->assertArrayNotHasKey('cirs_username', $_SESSION);
        $this->assertArrayNotHasKey('permissions',   $_SESSION);
        $this->assertArrayNotHasKey('role_name',     $_SESSION);

        // eNOTF-Kontext bleibt unangetastet
        $this->assertSame('fahrer', $_SESSION['enotf_position'] ?? null);
        $this->assertSame('t',      $_SESSION['enotf_session_token'] ?? null);
        $this->assertSame('A',      $_SESSION['fahrername'] ?? null);
    }

    #[Test]
    public function logout_enotf_crew_clearet_nur_crew_keys(): void
    {
        SessionManager::loginUser(
            ['id' => 5, 'username' => 'u', 'aktenid' => null, 'role' => 1, 'discord_id' => 'd'],
            ['admin'],
        );
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer' => ['name' => 'X', 'quali' => 'Y'],
        ], 'rtw');

        SessionManager::logoutEnotfCrew();

        $this->assertFalse(SessionManager::isEnotfActive());
        $this->assertArrayNotHasKey('protfzg',     $_SESSION);
        $this->assertArrayNotHasKey('fahrername',  $_SESSION);

        // Standard-User bleibt eingeloggt
        $this->assertTrue(SessionManager::isLoggedIn());
        $this->assertSame(5, SessionManager::userId());
    }

    #[Test]
    public function logout_einsatz_clearet_nur_einsatz_keys(): void
    {
        SessionManager::loginUser(
            ['id' => 5, 'username' => 'u', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd'],
            [],
        );
        SessionManager::loginEinsatz(1, 'LF', 5, 'Operator');

        SessionManager::logoutEinsatz();

        $this->assertFalse(SessionManager::isEinsatzActive());
        $this->assertArrayNotHasKey('einsatz_vehicle_name',  $_SESSION);
        $this->assertArrayNotHasKey('einsatz_operator_name', $_SESSION);

        // Standard-User bleibt eingeloggt
        $this->assertTrue(SessionManager::isLoggedIn());
    }

    #[Test]
    public function low_level_helper_funktionieren(): void
    {
        SessionManager::set('custom_key', 'value');
        $this->assertTrue(SessionManager::has('custom_key'));
        $this->assertSame('value', SessionManager::get('custom_key'));
        $this->assertSame('default', SessionManager::get('not_set', 'default'));

        SessionManager::forget('custom_key');
        $this->assertFalse(SessionManager::has('custom_key'));
        $this->assertNull(SessionManager::get('custom_key'));
    }
}
