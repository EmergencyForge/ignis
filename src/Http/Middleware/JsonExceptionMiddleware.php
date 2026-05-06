<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;

/**
 * JsonExceptionMiddleware — wandelt die beiden „tragenden" Exceptions
 * für API-Responses zentral in saubere JSON-Antworten um.
 *
 *   - `ValidationException` (aus FormRequest::validate) → 422 mit Field-Errors
 *   - `AuthorizationException` (aus Gate::authorize)   → 403 mit Message
 *
 * Als ÄUSSERSTES Middleware im API-Stack eingesetzt, damit Controller-Code
 * keine eigenen try/catch-Blöcke für diese Fälle mehr braucht. Für
 * HTML-Routes (Formulare mit Flash-Messages) wird diese Middleware bewusst
 * NICHT eingesetzt — dort catchen die Controller selbst, um Flash::error()
 * + Redirect auszulösen.
 */
final class JsonExceptionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->firstError() ?? 'Validierung fehlgeschlagen.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
