<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Http;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

/**
 * CsrfMiddleware — Cross-Site-Schutz für die v2-Web-Form-POSTs
 * (Nachfolger der reinen SameOriginMiddleware, jetzt mit echtem Token).
 *
 * Die v2-Session-Cookies laufen wegen des FiveM-iframes mit
 * SameSite=None; Secure (SessionManager-iframePaths) — der Browser hängt
 * sie damit auch an Form-POSTs, die eine FREMDE Seite abschickt. Deshalb
 * zweistufige Prüfung für schreibende Requests:
 *
 * 1. **CSRF-Token** (Hidden-Field `_csrf` bzw. Header `X-Csrf-Token`,
 *    siehe Csrf-Helper): stimmt es per hash_equals mit dem Session-Token
 *    überein → durch. Alle v2-Formulare und qm.js senden es mit.
 *
 * 2. **Origin-Fallback** (Token fehlt oder falsch): Origin-Header
 *    (ersatzweise Referer) muss denselben Host tragen wie der Request,
 *    sonst 403. Fehlen BEIDE Header, wird durchgelassen: der FiveM-CEF
 *    und ältere Clients senden nicht zuverlässig einen Origin — Browser
 *    schicken bei Cross-Site-Form-POSTs den Origin dagegen immer mit,
 *    ein komplett headerloser POST kommt also nicht aus einem fremden
 *    Browser-Kontext. Ein Origin ohne parsebaren Host (z. B. "null" aus
 *    Sandbox-iframes) wird abgewiesen.
 *
 * Netto: Cross-Site-Posts ohne gültiges Token und mit fremdem Origin
 * scheitern; Alt-Clients ohne Token bleiben über den Origin-Fallback
 * funktionsfähig.
 *
 * Nur für die Web-Routen gedacht. Die JSON-API unter /api/enotf-v2/…
 * braucht den Check nicht: deren Clients senden Content-Type
 * application/json, was cross-site einen CORS-Preflight erzwingt.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function process(Request $request, callable $next): Response
    {
        if (!in_array(strtoupper($request->method), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        // Stufe 1: gültiges CSRF-Token (Formular-Feld oder Header) → durch
        $candidate = $request->post[Csrf::FIELD_NAME] ?? null;
        if (!is_string($candidate) || $candidate === '') {
            $candidate = $request->header(Csrf::HEADER_NAME);
        }
        if (is_string($candidate) && Csrf::isValid($candidate)) {
            return $next($request);
        }

        // Stufe 2: Same-Origin-Heuristik (Verhalten der früheren
        // SameOriginMiddleware, unverändert)
        $source = $request->header('Origin');
        if ($source === null || $source === '') {
            $source = $request->header('Referer');
        }
        if ($source === null || $source === '') {
            return $next($request);
        }

        $sourceHost  = strtolower((string) parse_url($source, PHP_URL_HOST));
        $requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($request->server['HTTP_HOST'] ?? '')));

        if ($sourceHost === '' || $requestHost === '' || $sourceHost !== $requestHost) {
            return Response::text('Anfrage abgelehnt.', 403);
        }

        return $next($request);
    }
}
