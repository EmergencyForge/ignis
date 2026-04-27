<?php

namespace App\Session;

/**
 * SessionManager - Zentrale Session-Verwaltung mit Sicherheitsoptimierungen
 * 
 * Kompatibel mit:
 * - PHP 7.2+ (mit Fallbacks für ältere Versionen)
 * - Apache, NGINX, LiteSpeed
 * - Shared Hosting, VPS, Dedicated
 * - Reverse Proxies (CloudFlare, etc.)
 * - iframe-Einbettung (z.B. FiveM CEF)
 */
class SessionManager
{
    /**
     * Konfiguriert und startet die Session sicher
     * Kann mehrfach aufgerufen werden - startet nur einmal
     */
    public static function start(): void
    {
        // Wenn Session bereits aktiv, nichts tun (keine Warnings)
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Session-Konfiguration nur wenn Session noch nicht gestartet
        if (session_status() === PHP_SESSION_NONE) {
            self::configure();
            session_start();
        }
    }

    /**
     * Setzt sichere Session-Konfiguration
     * MUSS vor session_start() aufgerufen werden
     */
    private static function configure(): void
    {
        // Session-Lifetime: 2 Stunden
        @ini_set('session.gc_maxlifetime', '7200');

        // Sicherheit: Cookie nur via HTTP zugänglich (kein JavaScript)
        @ini_set('session.cookie_httponly', '1');

        // Sicherheit: Verhindert Session-Fixation Angriffe (PHP 5.5.2+)
        @ini_set('session.use_strict_mode', '1');

        // Sicherheit: CSRF-Schutz via SameSite (nur PHP 7.3+)
        // WICHTIG: Für iframe-Nutzung (z.B. FiveM) muss SameSite=None + Secure gesetzt werden
        if (PHP_VERSION_ID >= 70300) {
            if (self::isIframeContext()) {
                // iframe-Kontext: SameSite=None erlaubt Cross-Site Cookies
                // Erfordert HTTPS (Secure-Flag)
                @ini_set('session.cookie_samesite', 'None');
                @ini_set('session.cookie_secure', '1');
            } else {
                // Normaler Kontext: Lax ist sicherer
                @ini_set('session.cookie_samesite', 'Lax');
                // Secure nur wenn HTTPS
                if (self::isHttps()) {
                    @ini_set('session.cookie_secure', '1');
                }
            }
        } else {
            // PHP < 7.3: Nur Secure setzen wenn HTTPS
            if (self::isHttps()) {
                @ini_set('session.cookie_secure', '1');
            }
        }

        // Performance: Session-ID nur in Cookie, nicht in URL
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
    }

    /**
     * Erkennt ob die Anfrage aus einem iframe-Kontext kommt
     * (z.B. FiveM CEF, eingebettete Widgets)
     */
    private static function isIframeContext(): bool
    {
        // Methode 1: Sec-Fetch-Dest Header (moderne Browser)
        if (!empty($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe') {
            return true;
        }

        // Methode 2: Bestimmte Pfade die typischerweise in iframes laufen
        $iframePaths = ['/enotf/', '/einsatz/'];
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ($iframePaths as $path) {
            if (strpos($requestUri, $path) !== false) {
                return true;
            }
        }

        // Methode 3: Custom Header vom Game-Client
        if (!empty($_SERVER['HTTP_X_IFRAME_REQUEST'])) {
            return true;
        }

        // Methode 4: Referer von anderer Domain (Cross-Site)
        if (!empty($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_HOST'])) {
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            if ($refererHost && $refererHost !== $_SERVER['HTTP_HOST']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft ob die Verbindung über HTTPS läuft
     */
    private static function isHttps(): bool
    {
        // Standard HTTPS Check
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Standard Port Check
        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        // Hinter Proxy/Load Balancer (AWS, DigitalOcean, etc.)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Alternative Forward-Header
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        // CloudFlare
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = @json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }

        // Plesk
        if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on') {
            return true;
        }

        return false;
    }

    /**
     * Zerstört die Session sicher (für Logout)
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Session-Daten löschen
            $_SESSION = [];

            // Session-Cookie löschen
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            // Session zerstören
            session_destroy();
        }
    }

    /**
     * Regeneriert die Session-ID (nach Login empfohlen)
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // High-Level Login-API
    //
    // Eine Methode pro Auth-Kontext (siehe reference_auth_contexts.md):
    // Standard, eNOTF-Crew, Einsatz/FireTab, FiveM-Character, Klinikcode.
    // Vorher waren die Schreibzugriffe in Controllern und Templates
    // verstreut, was bei 5 parallelen Auth-Layern fragil war.
    // ──────────────────────────────────────────────────────────────────

    /**
     * Standard-User-Login (Discord-OAuth, Login-Form).
     *
     * @param array $user        Row aus intra_users
     * @param array $permissions Liste der aufgelösten Permission-Strings
     */
    public static function loginUser(array $user, array $permissions = []): void
    {
        self::start();
        self::regenerate();

        $_SESSION['userid']             = $user['id'] ?? null;
        $_SESSION['cirs_username']      = $user['username'] ?? '';
        $_SESSION['aktenid']            = $user['aktenid'] ?? null;
        $_SESSION['role']               = $user['role'] ?? null;
        $_SESSION['discordtag']         = $user['discord_id'] ?? null;
        $_SESSION['permissions']        = $permissions;
        $_SESSION['permissions_loaded'] = time();
    }

    /**
     * Setzt Rollen-Detail-Keys (color/name/priority) — wird von
     * Permissions::loadRoleData() aufgerufen, sobald die Rolle aufgelöst
     * ist. Getrennt von loginUser(), weil die Rolle erst NACH dem Login
     * lazy aus der DB geholt wird.
     */
    public static function setRoleDetails(?int $id, ?string $name, ?string $color, ?int $priority): void
    {
        $_SESSION['role_id']       = $id;
        $_SESSION['role_name']     = $name;
        $_SESSION['role_color']    = $color;
        $_SESSION['role_priority'] = $priority;
    }

    /**
     * Login der eNOTF-Crew (Fahrer + Beifahrer + optional Praktikant).
     *
     * @param array $crew {fahrer:{name,quali}, beifahrer:{name,quali}, praktikant?:{name,quali}}
     */
    /**
     * @param int|string|null $protokollFzg Vehicle-Identifier (Legacy:
     * gemischter Typ — historisch wurde der Wert direkt aus `$_SESSION
     * ['protfzg']` durchgeschoben, was sowohl ints als auch strings
     * erlaubte. Wir typen das hier bewusst weich, um beim Migrieren
     * keine Behavior-Drift zu erzeugen.)
     */
    public static function loginEnotfCrew(string $position, string $sessionToken, array $crew, int|string|null $protokollFzg = null): void
    {
        self::start();

        $_SESSION['enotf_position']      = $position;
        $_SESSION['enotf_session_token'] = $sessionToken;
        $_SESSION['fahrername']          = $crew['fahrer']['name'] ?? '';
        $_SESSION['fahrerquali']         = $crew['fahrer']['quali'] ?? '';
        $_SESSION['beifahrername']       = $crew['beifahrer']['name'] ?? '';
        $_SESSION['beifahrerquali']      = $crew['beifahrer']['quali'] ?? '';
        $_SESSION['praktikantname']      = $crew['praktikant']['name'] ?? '';
        $_SESSION['praktikantquali']     = $crew['praktikant']['quali'] ?? '';

        if ($protokollFzg !== null) {
            $_SESSION['protfzg'] = $protokollFzg;
        }
    }

    /**
     * Login eines FireTab-Operators auf einem Einsatzfahrzeug.
     */
    public static function loginEinsatz(int $vehicleId, string $vehicleName, int $operatorId, string $operatorName): void
    {
        self::start();

        $_SESSION['einsatz_vehicle_id']    = $vehicleId;
        $_SESSION['einsatz_vehicle_name']  = $vehicleName;
        $_SESSION['einsatz_operator_id']   = $operatorId;
        $_SESSION['einsatz_operator_name'] = $operatorName;
    }

    /**
     * Login eines FiveM-Charakters (CitizenFX-User-Agent).
     *
     * `$charId` ist optional: der identify-Endpoint bekommt die Char-ID
     * nicht in jedem Fall (z.B. wenn der FiveM-Server sie nicht kennt).
     */
    public static function loginCharacter(int|string|null $charId, string $charJob, string $charName): void
    {
        self::start();

        $_SESSION['char_job']  = $charJob;
        $_SESSION['char_name'] = $charName;
        if ($charId !== null && $charId !== '') {
            $_SESSION['char_id'] = $charId;
        }
    }

    /**
     * Klinikcode-Zugang für die Hospital-Availability-Schnittstelle.
     */
    public static function loginKlinikcode(string $enr): void
    {
        self::start();

        $_SESSION['klinik_access_enr']  = $enr;
        $_SESSION['klinik_access_time'] = time();
    }

    /**
     * Loggt nur den Standard-User aus (eNOTF / Einsatz bleibt aktiv).
     */
    public static function logoutUser(): void
    {
        $keys = [
            'userid', 'cirs_username', 'aktenid', 'role', 'discordtag',
            'permissions', 'permissions_loaded',
            'role_id', 'role_name', 'role_color', 'role_priority',
        ];
        foreach ($keys as $k) {
            unset($_SESSION[$k]);
        }
    }

    /**
     * Loggt nur die eNOTF-Crew aus.
     */
    public static function logoutEnotfCrew(): void
    {
        $keys = [
            'enotf_position', 'enotf_session_token',
            'fahrername', 'fahrerquali',
            'beifahrername', 'beifahrerquali',
            'praktikantname', 'praktikantquali',
            'protfzg',
        ];
        foreach ($keys as $k) {
            unset($_SESSION[$k]);
        }
    }

    /**
     * Loggt den Einsatz-Operator aus.
     */
    public static function logoutEinsatz(): void
    {
        $keys = [
            'einsatz_vehicle_id', 'einsatz_vehicle_name',
            'einsatz_operator_id', 'einsatz_operator_name',
            'aktenid',
        ];
        foreach ($keys as $k) {
            unset($_SESSION[$k]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Reader-API
    // ──────────────────────────────────────────────────────────────────

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['userid']);
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['userid'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public static function username(): ?string
    {
        return $_SESSION['cirs_username'] ?? null;
    }

    /**
     * Aktuell aufgelöste Permission-Strings für den eingeloggten User.
     *
     * @return string[]
     */
    public static function permissions(): array
    {
        $p = $_SESSION['permissions'] ?? [];
        return is_array($p) ? $p : [];
    }

    public static function isEnotfActive(): bool
    {
        return !empty($_SESSION['enotf_session_token']);
    }

    public static function isEinsatzActive(): bool
    {
        return !empty($_SESSION['einsatz_vehicle_id']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Low-Level Convenience für Stellen, die noch direkt auf $_SESSION
    // zugreifen — Notnagel, damit Aufrufer nicht ständig in $_SESSION
    // greifen müssen, ohne dass wir jeden Detail-Key in der API führen.
    // ──────────────────────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Redirect-After-Login
    // ──────────────────────────────────────────────────────────────────

    /**
     * Speichert die URL, auf die nach erfolgreichem Login redirected werden
     * soll. Wird in Auth-Middlewares aufgerufen, bevor das Login-Form
     * gezeigt wird.
     */
    public static function setRedirectUrl(string $url): void
    {
        self::start();
        $_SESSION['redirect_url'] = $url;
    }

    /**
     * Holt die hinterlegte Redirect-URL und löscht sie aus der Session
     * (atomares „pull"). Liefert null, wenn keine gesetzt war.
     */
    public static function pullRedirectUrl(): ?string
    {
        self::start();
        $url = $_SESSION['redirect_url'] ?? null;
        unset($_SESSION['redirect_url']);
        return $url;
    }

    /**
     * Speichert die Request-URI als Redirect-Ziel für das nächste Login.
     * Notnagel mit fester REQUEST_URI-Quelle, damit jeder Aufrufer
     * dieselbe Logik nutzt.
     */
    public static function setRedirectFromRequest(): void
    {
        self::setRedirectUrl($_SERVER['REQUEST_URI'] ?? '/');
    }

    // ──────────────────────────────────────────────────────────────────
    // eNOTF Crew-Update (partieller Refresh ohne Re-Login)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Aktualisiert die Crew-Daten einer aktiven eNOTF-Session, ohne
     * Position oder Session-Token anzufassen. Wird vom Crew-Sync-API
     * genutzt, wenn ein anderes Crewmitglied den Stand modifiziert hat.
     *
     * @param array<string, string|null> $crew  Keys: fahrername, fahrerquali,
     *                                          beifahrername, beifahrerquali,
     *                                          praktikantname, praktikantquali
     */
    public static function updateEnotfCrew(array $crew): void
    {
        self::start();
        foreach ([
            'fahrername', 'fahrerquali',
            'beifahrername', 'beifahrerquali',
            'praktikantname', 'praktikantquali',
        ] as $key) {
            if (array_key_exists($key, $crew)) {
                $_SESSION[$key] = $crew[$key];
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // PIN-Lockscreen
    // ──────────────────────────────────────────────────────────────────

    /**
     * Markiert die Session als PIN-verifiziert und merkt sich die
     * Return-URL nach erfolgreicher PIN-Eingabe.
     */
    public static function setPinVerified(bool $verified, ?string $returnUrl = null): void
    {
        self::start();
        if ($verified) {
            $_SESSION['pin_verified']      = true;
            $_SESSION['pin_last_activity'] = time();
            if ($returnUrl !== null) {
                $_SESSION['pin_return_url'] = $returnUrl;
            }
        } else {
            unset($_SESSION['pin_verified'], $_SESSION['pin_last_activity']);
        }
    }

    /**
     * Refresht den Activity-Timestamp für die PIN-Inactivity-Logic.
     */
    public static function touchPin(): void
    {
        self::start();
        $_SESSION['pin_last_activity'] = time();
    }

    /**
     * Speichert die URL, auf die nach erfolgreicher PIN-Eingabe
     * zurückgeleitet wird.
     */
    public static function setPinReturnUrl(string $url): void
    {
        self::start();
        $_SESSION['pin_return_url'] = $url;
    }

    /**
     * Liefert die PIN-Return-URL und löscht sie atomar (pull-Pattern).
     */
    public static function pullPinReturnUrl(): ?string
    {
        self::start();
        $url = $_SESSION['pin_return_url'] ?? null;
        unset($_SESSION['pin_return_url']);
        return $url;
    }

    /**
     * Entfernt alle PIN-bezogenen Session-Felder (Logout der PIN-Schicht).
     */
    public static function clearPin(): void
    {
        self::start();
        unset(
            $_SESSION['pin_verified'],
            $_SESSION['pin_last_activity'],
            $_SESSION['pin_return_url']
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Misc Flags
    // ──────────────────────────────────────────────────────────────────

    /**
     * Markiert, dass die nächste View-Log-Erfassung übersprungen werden
     * soll (z.B. bei einer Speicheraktion, die ein Reload triggert).
     * Wird vom Logger geprüft und nach dem Skip automatisch konsumiert.
     */
    public static function skipNextViewLog(): void
    {
        self::start();
        $_SESSION['skip_next_view_log'] = true;
    }

    /**
     * Speichert pending Composer-Update-State. Wird vom SystemUpdater
     * gesetzt, wenn ein Update nicht in einem Request abgeschlossen werden
     * konnte und der nächste Request den Vorgang fortsetzen soll.
     */
    public static function setComposerPending(?array $data): void
    {
        self::start();
        if ($data === null) {
            unset($_SESSION['composer_pending']);
        } else {
            $_SESSION['composer_pending'] = $data;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Permissions-Cache (TTL-basiertes Refresh in config.php)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Speichert die aufgelösten Permission-Strings und markiert den
     * Lade-Zeitpunkt. Wird beim Login und nach Ablauf des TTL aus
     * config.php aufgerufen.
     *
     * @param string[] $permissions
     */
    public static function setPermissions(array $permissions): void
    {
        self::start();
        $_SESSION['permissions']        = $permissions;
        $_SESSION['permissions_loaded'] = time();
    }

    /**
     * Liefert das Alter (Sekunden) des Permissions-Caches. Liefert
     * `PHP_INT_MAX`, wenn noch nie geladen wurde — so dass jeder TTL-
     * Vergleich automatisch zu einem Refresh führt.
     */
    public static function permissionsAge(): int
    {
        $loaded = $_SESSION['permissions_loaded'] ?? 0;
        if (!is_int($loaded) || $loaded <= 0) {
            return PHP_INT_MAX;
        }
        return time() - $loaded;
    }

    // ──────────────────────────────────────────────────────────────────
    // OAuth2-State (CSRF-Schutz für Discord-OAuth)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Speichert den vom OAuth-Provider erzeugten State-Token mit Zeitstempel.
     * Wird vor dem Redirect zum Authorization-Server gesetzt und vom
     * Callback wieder gegen den vom Server zurückgereichten State geprüft.
     */
    public static function setOAuth2State(string $state): void
    {
        self::start();
        $_SESSION['oauth2state']      = $state;
        $_SESSION['oauth2state_time'] = time();
    }

    /**
     * Validiert einen OAuth-State und konsumiert ihn (atomar). Liefert:
     *   - 'ok'         wenn State gesetzt, jung genug (≤5min) und gleich
     *   - 'missing'    wenn kein State in der Session
     *   - 'expired'    wenn älter als 5 Minuten
     *   - 'mismatch'   wenn State nicht übereinstimmt
     *
     * In allen Fehler-Fällen werden die State-Felder gelöscht, damit ein
     * Replay nicht möglich ist.
     */
    public static function consumeOAuth2State(string $providedState): string
    {
        self::start();
        $stored = $_SESSION['oauth2state']      ?? null;
        $time   = $_SESSION['oauth2state_time'] ?? null;

        unset($_SESSION['oauth2state'], $_SESSION['oauth2state_time']);

        if ($stored === null || $time === null) {
            return 'missing';
        }
        if (time() - (int) $time > 300) {
            return 'expired';
        }
        if ($providedState === '' || $providedState !== $stored) {
            return 'mismatch';
        }
        return 'ok';
    }

    // ──────────────────────────────────────────────────────────────────
    // Registrierungs-Flow (Einladungs-Codes + Login-Fehler)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Setzt eine Fehlermeldung, die das Login-Formular beim nächsten
     * Render anzeigt und anschließend wieder löscht.
     */
    public static function setRegistrationError(string $message): void
    {
        self::start();
        $_SESSION['registration_error'] = $message;
    }

    /**
     * Liefert die hinterlegte Registrierungs-Fehlermeldung und löscht
     * sie atomar (pull-Pattern).
     */
    public static function pullRegistrationError(): ?string
    {
        self::start();
        $msg = $_SESSION['registration_error'] ?? null;
        unset($_SESSION['registration_error']);
        return is_string($msg) ? $msg : null;
    }

    /**
     * Speichert den Registrierungs-/Einladungs-Code, den der User auf der
     * Login-Seite oder via /invite eingegeben hat. Wird im OAuth-Callback
     * verifiziert und nach erfolgreicher Registrierung wieder gelöscht.
     */
    public static function setRegistrationCode(string $code): void
    {
        self::start();
        $_SESSION['registration_code'] = $code;
    }

    public static function getRegistrationCode(): ?string
    {
        $code = $_SESSION['registration_code'] ?? null;
        return is_string($code) ? $code : null;
    }

    public static function clearRegistrationCode(): void
    {
        unset($_SESSION['registration_code']);
    }

    /**
     * Entfernt den Klinikcode-Zugang (Hospital-Availability-Schnittstelle).
     * Wird nach Ablauf des KLINIK_WINDOW_SECONDS-Fensters aufgerufen.
     */
    public static function clearKlinikAccess(): void
    {
        unset($_SESSION['klinik_access_enr'], $_SESSION['klinik_access_time']);
    }

    /**
     * Entfernt alle Session-Keys mit dem gegebenen Prefix. Optional kann
     * ein Key vom Löschen ausgenommen werden — nützlich, um beim Wechsel
     * auf eine View alle anderen `*_viewed_*`-Marker zu entfernen.
     */
    public static function forgetByPrefix(string $prefix, ?string $exceptKey = null): void
    {
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with((string) $key, $prefix) && $key !== $exceptKey) {
                unset($_SESSION[$key]);
            }
        }
    }
}
