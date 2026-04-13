<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Prüft API-Key-Authentifizierung für Maschine-zu-Maschine-Endpoints
 * (FiveM-Server → intraRP).
 *
 * Verhalten identisch zu `ApiMiddleware::requireApiKey()`:
 *   - Localhost (127.0.0.1, ::1) wird durchgelassen
 *   - Key kann im Header `X-API-Key`, in der Query `api_key` oder im
 *     JSON-Body `intraRP_API_Key` stehen — intraRP-FiveM-Clients nutzen
 *     historisch den Body-Key, deshalb müssen wir das unterstützen
 *   - Vergleich gegen die Konstante `API_KEY` aus der DB-Config
 *
 * Nach erfolgreichem Check wird `api_auth=true` als Request-Attribut
 * gesetzt, damit nachfolgende Middlewares/Controller das erkennen können.
 */
final class ApiKeyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $remote = $request->server['REMOTE_ADDR'] ?? '';
        if (in_array($remote, ['127.0.0.1', '::1'], true)) {
            return $next($request->withAttribute('api_auth', 'localhost'));
        }

        $configured = defined('API_KEY') ? (string) constant('API_KEY') : '';
        if ($configured === '' || $configured === 'CHANGE_ME') {
            return Response::json(['success' => false, 'message' => 'API-Key nicht konfiguriert'], 503);
        }

        $provided = $request->header('X-API-Key')
            ?? ($request->query['api_key'] ?? null)
            ?? $this->keyFromJsonBody($request);

        if (!is_string($provided) || $provided === '' || !hash_equals($configured, $provided)) {
            return Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
        }

        return $next($request->withAttribute('api_auth', 'key'));
    }

    private function keyFromJsonBody(Request $request): ?string
    {
        $json = $request->json();
        if (!is_array($json)) {
            return null;
        }
        $key = $json['intraRP_API_Key'] ?? $json['api_key'] ?? null;
        return is_string($key) ? $key : null;
    }
}
