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
 * - Custom Session Handler (Redis, Memcached, DB)
 * 
 * Verwendung:
 *   use App\Session\SessionManager;
 *   SessionManager::start();
 * 
 * Oder einfach config.php includen - das startet die Session automatisch.
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
     * 
     * Verwendet @-Operator um Fehler bei Shared Hosting zu unterdrücken
     * wo ini_set() möglicherweise eingeschränkt ist
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
        if (PHP_VERSION_ID >= 70300) {
            @ini_set('session.cookie_samesite', 'Lax');
        }

        // Sicherheit: Cookie nur über HTTPS senden (wenn verfügbar)
        if (self::isHttps()) {
            @ini_set('session.cookie_secure', '1');
        }

        // Performance: Session-ID nur in Cookie, nicht in URL
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
    }

    /**
     * Prüft ob die Verbindung über HTTPS läuft
     * Unterstützt verschiedene Reverse-Proxy-Konfigurationen
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
     * Verhindert Session-Fixation-Angriffe
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
