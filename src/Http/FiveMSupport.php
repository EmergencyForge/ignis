<?php

declare(strict_types=1);

namespace App\Http;

/**
 * FiveMSupport — Hilfsfunktionen für intraRP-Pages, die im FiveM-Client
 * (CitizenFX-CEF-Webview) angezeigt werden.
 *
 * Kontext: FiveM-Server zeigen intraRP-Seiten in einem in-Game-Browser an.
 * Das CEF braucht spezielle Cookie-Settings (SameSite=None, Secure) und darf
 * keine restriktiven CSP/X-Frame-Options-Header haben, sonst kann iframe-
 * Embedding nicht funktionieren. Die `.htaccess` regelt das auf Webserver-
 * Ebene; auf PHP-Ebene müssen Pages, die intern Header setzen, die für
 * CitizenFX-User-Agents wieder entfernen.
 *
 * Diese Klasse extrahiert das Pattern, das vorher in jedem
 * `einsatz/*.php`-File mehrfach vorkam, an einen Ort.
 *
 * Aufruf am Anfang einer Page:
 *
 *     \App\Http\FiveMSupport::prepareCookiesAndHeaders();
 */
final class FiveMSupport
{
    /**
     * Setzt SameSite=None+Secure für HTTPS-Sessions (notwendig für iframe-
     * Embedding) und entfernt die CSP/X-Frame-Headers für CitizenFX-Clients,
     * damit das CEF-Webview die Seite einbetten darf.
     *
     * Idempotent: kann mehrfach aufgerufen werden.
     */
    public static function prepareCookiesAndHeaders(): void
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            @ini_set('session.cookie_samesite', 'None');
            @ini_set('session.cookie_secure', '1');
        }

        if (self::isCitizenFx()) {
            // Wir entfernen die Header, .htaccess setzt sie für CitizenFX
            // gar nicht erst — der header_remove() ist eine Sicherheitsnetz
            // falls eine Page sie selbst gesetzt hat.
            header_remove('Content-Security-Policy');
            header_remove('X-Frame-Options');
        }
    }

    /**
     * Prüft den User-Agent auf CitizenFX-Marker.
     */
    public static function isCitizenFx(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return str_contains($ua, 'CitizenFX');
    }
}
