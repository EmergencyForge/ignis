<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Http;

/**
 * Csrf — Token-Verwaltung für die v2-Web-Form-POSTs.
 *
 * Pro PHP-Session EIN Token (random_bytes(32) als Hex), abgelegt in
 * $_SESSION. Die Templates rendern es als Hidden-Field `_csrf` in jedes
 * POST-Formular; JS-Clients (qm.js) schicken es als Header
 * `X-Csrf-Token`. Geprüft wird in der CsrfMiddleware per hash_equals.
 *
 * Die Session ist zum Renderzeitpunkt immer schon gestartet
 * (SessionManager::start() im Config-Bootstrap) — auch auf dem
 * Lockscreen, dessen PIN-Flow ohnehin eine Session voraussetzt.
 */
final class Csrf
{
    public const SESSION_KEY = 'enotf_v2_csrf_token';
    public const FIELD_NAME  = '_csrf';
    public const HEADER_NAME = 'X-Csrf-Token';

    /**
     * Liefert das Token der aktuellen Session, erzeugt es beim ersten
     * Aufruf. Ohne aktive Session (CLI, Tests) kommt '' zurück.
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /**
     * Timing-sicherer Vergleich eines eingereichten Tokens gegen die
     * Session. False, wenn die Session (noch) kein Token trägt — ein
     * fehlendes Session-Token darf niemals "alles gültig" bedeuten.
     */
    public static function isValid(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        $token = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($token) && $token !== '' && hash_equals($token, $candidate);
    }
}
