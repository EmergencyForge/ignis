<?php

declare(strict_types=1);

namespace Plugin\Enotf\Http;

use EmergencyForge\Http\Middleware\MiddlewareInterface;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Enotf\Policies\EnotfPolicy;

/**
 * Crew-Endpoints der v1-API: ignis-Konto oder Fahrzeug-Login. Läuft hinter
 * AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), ohne diese Prüfung wäre die API
 * bei abgeschaltetem Konto-Zwang für jeden offen.
 */
final class CrewSessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (!empty($_SESSION['userid']) || EnotfPolicy::hasCrewSession()) {
            return $next($request);
        }

        return Response::json(['success' => false, 'message' => 'Nicht angemeldet'], 401);
    }
}
