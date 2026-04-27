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

    // ─── Redirect-After-Login ────────────────────────────────────────

    #[Test]
    public function redirect_url_set_und_pull_arbeitet_atomar(): void
    {
        SessionManager::setRedirectUrl('/benutzer/list');
        $this->assertSame('/benutzer/list', $_SESSION['redirect_url']);

        $first = SessionManager::pullRedirectUrl();
        $this->assertSame('/benutzer/list', $first);

        // Zweiter Pull → null, der Wert wurde konsumiert
        $this->assertNull(SessionManager::pullRedirectUrl());
        $this->assertArrayNotHasKey('redirect_url', $_SESSION);
    }

    #[Test]
    public function redirect_from_request_liest_request_uri(): void
    {
        $_SERVER['REQUEST_URI'] = '/einsatz/view?id=42';
        SessionManager::setRedirectFromRequest();
        $this->assertSame('/einsatz/view?id=42', $_SESSION['redirect_url']);
        unset($_SERVER['REQUEST_URI']);
    }

    // ─── PIN-Lockscreen ──────────────────────────────────────────────

    #[Test]
    public function pin_verified_setzt_und_loescht_zustand(): void
    {
        SessionManager::setPinVerified(true, '/enotf/list');
        $this->assertTrue($_SESSION['pin_verified']);
        $this->assertIsInt($_SESSION['pin_last_activity']);
        $this->assertSame('/enotf/list', $_SESSION['pin_return_url']);

        SessionManager::setPinVerified(false);
        $this->assertArrayNotHasKey('pin_verified', $_SESSION);
        $this->assertArrayNotHasKey('pin_last_activity', $_SESSION);
    }

    #[Test]
    public function touch_pin_aktualisiert_activity_timestamp(): void
    {
        SessionManager::setPinVerified(true);
        $first = $_SESSION['pin_last_activity'];

        // Activity-Timestamp absichtlich in die Vergangenheit setzen, damit
        // der touch sichtbar wirkt — sleep wäre zu langsam.
        $_SESSION['pin_last_activity'] = $first - 10;
        SessionManager::touchPin();

        $this->assertGreaterThan($first - 10, $_SESSION['pin_last_activity']);
    }

    #[Test]
    public function pin_return_url_set_und_pull_atomar(): void
    {
        SessionManager::setPinReturnUrl('/dashboard');
        $this->assertSame('/dashboard', SessionManager::pullPinReturnUrl());
        $this->assertNull(SessionManager::pullPinReturnUrl());
    }

    #[Test]
    public function clear_pin_entfernt_alle_pin_keys(): void
    {
        SessionManager::setPinVerified(true, '/foo');
        SessionManager::clearPin();
        $this->assertArrayNotHasKey('pin_verified', $_SESSION);
        $this->assertArrayNotHasKey('pin_last_activity', $_SESSION);
        $this->assertArrayNotHasKey('pin_return_url', $_SESSION);
    }

    // ─── eNOTF Crew Update ───────────────────────────────────────────

    #[Test]
    public function update_enotf_crew_aktualisiert_nur_uebergebene_keys(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', [
            'fahrer'     => ['name' => 'A', 'quali' => 'NS'],
            'beifahrer'  => ['name' => 'B', 'quali' => 'RS'],
            'praktikant' => ['name' => 'C', 'quali' => 'PA'],
        ]);

        // Nur Beifahrer ändern
        SessionManager::updateEnotfCrew([
            'beifahrername'  => 'NeuerBeifahrer',
            'beifahrerquali' => 'NotSan',
        ]);

        $this->assertSame('A',              $_SESSION['fahrername']);
        $this->assertSame('NS',             $_SESSION['fahrerquali']);
        $this->assertSame('NeuerBeifahrer', $_SESSION['beifahrername']);
        $this->assertSame('NotSan',         $_SESSION['beifahrerquali']);
        $this->assertSame('C',              $_SESSION['praktikantname']);
    }

    // ─── OAuth2-State (CSRF) ─────────────────────────────────────────

    #[Test]
    public function oauth2_state_consume_returns_ok_for_matching_state(): void
    {
        SessionManager::setOAuth2State('abc123');
        $this->assertSame('ok', SessionManager::consumeOAuth2State('abc123'));

        // Konsumiert → kein State mehr
        $this->assertArrayNotHasKey('oauth2state', $_SESSION);
    }

    #[Test]
    public function oauth2_state_consume_returns_missing_when_no_state_set(): void
    {
        $this->assertSame('missing', SessionManager::consumeOAuth2State('anything'));
    }

    #[Test]
    public function oauth2_state_consume_returns_expired_after_5min(): void
    {
        SessionManager::setOAuth2State('xyz');
        // Manuell auf 6 Minuten alt setzen
        $_SESSION['oauth2state_time'] = time() - 360;

        $this->assertSame('expired', SessionManager::consumeOAuth2State('xyz'));
        $this->assertArrayNotHasKey('oauth2state', $_SESSION);
    }

    #[Test]
    public function oauth2_state_consume_returns_mismatch_for_wrong_state(): void
    {
        SessionManager::setOAuth2State('correct');
        $this->assertSame('mismatch', SessionManager::consumeOAuth2State('attacker'));
        // State trotzdem konsumiert (kein Replay)
        $this->assertArrayNotHasKey('oauth2state', $_SESSION);
    }

    #[Test]
    public function oauth2_state_consume_rejects_empty_string(): void
    {
        SessionManager::setOAuth2State('correct');
        $this->assertSame('mismatch', SessionManager::consumeOAuth2State(''));
    }

    // ─── Registrierungs-Flow ─────────────────────────────────────────

    #[Test]
    public function registration_error_pull_atomar(): void
    {
        SessionManager::setRegistrationError('Code abgelaufen');
        $this->assertSame('Code abgelaufen', SessionManager::pullRegistrationError());
        $this->assertNull(SessionManager::pullRegistrationError());
    }

    #[Test]
    public function registration_code_set_get_clear(): void
    {
        SessionManager::setRegistrationCode('INV-2026-001');
        $this->assertSame('INV-2026-001', SessionManager::getRegistrationCode());

        SessionManager::clearRegistrationCode();
        $this->assertNull(SessionManager::getRegistrationCode());
    }

    // ─── Permissions-Cache ───────────────────────────────────────────

    #[Test]
    public function set_permissions_speichert_liste_und_zeitstempel(): void
    {
        $before = time();
        SessionManager::setPermissions(['admin', 'edivi.view']);
        $this->assertSame(['admin', 'edivi.view'], $_SESSION['permissions']);
        $this->assertGreaterThanOrEqual($before, $_SESSION['permissions_loaded']);
    }

    #[Test]
    public function permissions_age_returns_int_max_when_never_loaded(): void
    {
        $this->assertSame(PHP_INT_MAX, SessionManager::permissionsAge());
    }

    #[Test]
    public function permissions_age_returns_seconds_since_load(): void
    {
        SessionManager::setPermissions(['admin']);
        $_SESSION['permissions_loaded'] = time() - 42;
        $this->assertGreaterThanOrEqual(42, SessionManager::permissionsAge());
        $this->assertLessThanOrEqual(43, SessionManager::permissionsAge());
    }

    // ─── Misc Flags ──────────────────────────────────────────────────

    #[Test]
    public function skip_next_view_log_setzt_flag(): void
    {
        SessionManager::skipNextViewLog();
        $this->assertTrue($_SESSION['skip_next_view_log']);
    }

    #[Test]
    public function set_composer_pending_setzt_und_clearet(): void
    {
        SessionManager::setComposerPending(['step' => 1, 'data' => 'foo']);
        $this->assertSame(['step' => 1, 'data' => 'foo'], $_SESSION['composer_pending']);

        SessionManager::setComposerPending(null);
        $this->assertArrayNotHasKey('composer_pending', $_SESSION);
    }

    #[Test]
    public function composer_pending_unterstuetzt_bool_flag_variant(): void
    {
        // Settings-Page nutzt das Flag-Verhalten: einfach true/false
        SessionManager::setComposerPending(true);
        $this->assertTrue(SessionManager::isComposerPending());
        $this->assertTrue($_SESSION['composer_pending']);

        SessionManager::setComposerPending(false);
        $this->assertFalse(SessionManager::isComposerPending());
        $this->assertArrayNotHasKey('composer_pending', $_SESSION);
    }

    #[Test]
    public function consume_composer_pending_returns_state_and_clears(): void
    {
        SessionManager::setComposerPending(true);
        $this->assertTrue(SessionManager::consumeComposerPending());

        // Zweiter Aufruf liefert false (atomar konsumiert)
        $this->assertFalse(SessionManager::consumeComposerPending());
        $this->assertArrayNotHasKey('composer_pending', $_SESSION);
    }

    #[Test]
    public function clear_klinik_access_entfernt_beide_keys(): void
    {
        SessionManager::loginKlinikcode('ENR-001');
        SessionManager::clearKlinikAccess();
        $this->assertArrayNotHasKey('klinik_access_enr', $_SESSION);
        $this->assertArrayNotHasKey('klinik_access_time', $_SESSION);
    }

    #[Test]
    public function forget_by_prefix_loescht_alle_passenden_keys(): void
    {
        $_SESSION['einsatz_viewed_1'] = true;
        $_SESSION['einsatz_viewed_2'] = true;
        $_SESSION['einsatz_viewed_3'] = true;
        $_SESSION['userid']           = 42;

        SessionManager::forgetByPrefix('einsatz_viewed_');

        $this->assertArrayNotHasKey('einsatz_viewed_1', $_SESSION);
        $this->assertArrayNotHasKey('einsatz_viewed_2', $_SESSION);
        $this->assertArrayNotHasKey('einsatz_viewed_3', $_SESSION);
        // Andere Keys bleiben unangetastet
        $this->assertSame(42, $_SESSION['userid']);
    }

    #[Test]
    public function forget_by_prefix_respektiert_except_key(): void
    {
        $_SESSION['einsatz_viewed_1'] = true;
        $_SESSION['einsatz_viewed_2'] = true;
        $_SESSION['einsatz_viewed_3'] = true;

        SessionManager::forgetByPrefix('einsatz_viewed_', 'einsatz_viewed_2');

        $this->assertArrayNotHasKey('einsatz_viewed_1', $_SESSION);
        $this->assertSame(true, $_SESSION['einsatz_viewed_2']);
        $this->assertArrayNotHasKey('einsatz_viewed_3', $_SESSION);
    }

    // ─── Cross-Context-Isolation ─────────────────────────────────────

    #[Test]
    public function login_character_mit_null_charid_setzt_nur_job_und_name(): void
    {
        SessionManager::loginCharacter(null, 'fire', 'John Doe');

        $this->assertSame('fire',     $_SESSION['char_job']);
        $this->assertSame('John Doe', $_SESSION['char_name']);
        $this->assertArrayNotHasKey('char_id', $_SESSION);
    }

    #[Test]
    public function alle_5_kontexte_koennen_parallel_aktiv_sein(): void
    {
        // Realistisches Scenario: Admin loggt sich ein, übernimmt eine
        // eNOTF-Crew, betritt einen Einsatz, identifiziert seinen FiveM-
        // Charakter und ruft eine Klinik-Schnittstelle auf — alle in
        // derselben Session.
        SessionManager::loginUser(
            ['id' => 1, 'username' => 'admin', 'aktenid' => 1, 'role' => 1, 'discord_id' => 'd1'],
            ['admin'],
        );
        SessionManager::loginEnotfCrew('fahrer', 'enotf-tok', [
            'fahrer' => ['name' => 'F', 'quali' => 'NS'],
        ]);
        SessionManager::loginEinsatz(7, 'LF', 1, 'Op');
        SessionManager::loginCharacter('char-1', 'fire', 'Name');
        SessionManager::loginKlinikcode('ENR-1');

        // Alle Kontexte sind unabhängig aktiv
        $this->assertTrue(SessionManager::isLoggedIn());
        $this->assertTrue(SessionManager::isEnotfActive());
        $this->assertTrue(SessionManager::isEinsatzActive());
        $this->assertSame('Name', $_SESSION['char_name']);
        $this->assertSame('ENR-1', $_SESSION['klinik_access_enr']);

        // Logout User → andere Kontexte überleben
        SessionManager::logoutUser();
        $this->assertFalse(SessionManager::isLoggedIn());
        $this->assertTrue(SessionManager::isEnotfActive());
        $this->assertTrue(SessionManager::isEinsatzActive());

        // Logout eNOTF → Einsatz und Char und Klinik überleben
        SessionManager::logoutEnotfCrew();
        $this->assertFalse(SessionManager::isEnotfActive());
        $this->assertTrue(SessionManager::isEinsatzActive());
        $this->assertSame('Name', $_SESSION['char_name'] ?? null);
        $this->assertSame('ENR-1', $_SESSION['klinik_access_enr'] ?? null);
    }
}
