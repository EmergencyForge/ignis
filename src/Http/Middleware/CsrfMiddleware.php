<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\CsrfProtection;

/**
 * Erzwingt einen gültigen CSRF-Token für state-ändernde Requests.
 *
 * Wird typischerweise an POST/PUT/PATCH/DELETE-Routen gehängt. GET-Requests
 * sollten keinen CSRF-Check brauchen (idempotent) — darum prüft die
 * Middleware die HTTP-Methode zuerst und greift nur bei schreibenden
 * Operationen.
 *
 * Token-Quellen (in Reihenfolge):
 *   1. JSON-Body `csrf_token`
 *   2. POST-Parameter `csrf_token`
 *   3. Header `X-CSRF-Token`
 *
 * Delegiert die Token-Validierung an die bestehende `CsrfProtection`-
 * Klasse — rotiert also den Token wie gewohnt nach erfolgreichem Check.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function process(Request $request, callable $next): Response
    {
        if (!in_array(strtoupper($request->method), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if ($token === null || !CsrfProtection::validateToken($token)) {
            return Response::json(['success' => false, 'message' => 'CSRF-Token ungültig oder abgelaufen'], 403);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $json = $request->json();
        if (is_array($json) && isset($json['csrf_token']) && is_string($json['csrf_token'])) {
            return $json['csrf_token'];
        }

        if (isset($request->post['csrf_token']) && is_string($request->post['csrf_token'])) {
            return $request->post['csrf_token'];
        }

        $header = $request->header('X-CSRF-Token');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }
}
