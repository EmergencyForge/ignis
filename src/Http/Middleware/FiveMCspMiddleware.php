<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * FiveM-CEF-Integration: Entfernt CSP- und X-Frame-Options-Header für
 * Requests, die vom FiveM-Client (CitizenFX User-Agent) kommen, damit
 * die Seite in der FiveM-UI eingebettet werden kann.
 *
 * Nicht-FiveM-Requests bekommen strengen CSP-Header zurück.
 *
 * Diese Middleware ersetzt die Apache-Konfiguration aus:
 *   - enotf/.htaccess
 *   - einsatz/.htaccess
 *
 * Wird nur an Routen gehängt, die tatsächlich in der FiveM-UI angezeigt
 * werden (eNOTF-Protokoll-Seiten, Einsatz-Tactical-Map). Andere Admin-
 * Routen behalten ihre normalen Security-Header, die an anderer Stelle
 * gesetzt werden.
 */
final class FiveMCspMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if ($request->isFiveM()) {
            // FiveM-Embed: Security-Header entfernen, damit die Seite
            // im CEF-Overlay rendern kann.
            return $response
                ->withoutHeader('Content-Security-Policy')
                ->withoutHeader('X-Frame-Options');
        }

        // Normale Browser: strikte Frame-Ancestors setzen (analog zur
        // existierenden Apache-Regel). https://* erlaubt Einbettung in
        // andere HTTPS-Sites (z.B. intraRP-Dashboards auf Subdomains).
        return $response
            ->withHeader('Content-Security-Policy', "frame-ancestors 'self' https://*");
    }
}
