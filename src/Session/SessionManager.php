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
}
