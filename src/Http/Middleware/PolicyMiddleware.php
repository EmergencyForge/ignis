<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Gate;
use App\Exceptions\AuthorizationException;
use App\Http\Request;
use App\Http\Response;

/**
 * Deklarative Policy-Prüfung auf Route-Ebene.
 *
 * Prüft eine Gate-Ability bevor der Controller-Handler aufgerufen wird.
 * Wenn die Ability nicht erlaubt ist, antwortet die Middleware mit einer
 * 403-Response (HTML-Redirect oder JSON, je nach Request-Art).
 *
 * Einsatz in Routen:
 *
 *     use App\Http\Middleware\PolicyMiddleware;
 *
 *     $router->get('/users/{id:\d+}',
 *         [UserController::class, 'show'],
 *         [new AuthMiddleware(), new PolicyMiddleware('user.view', resourceParam: 'id')]
 *     );
 *
 * Der `resourceParam`-Parameter sagt der Middleware, welcher Route-Parameter
 * als Resource-Argument an die Policy-Methode gereicht werden soll. Bei
 * null wird die Policy-Methode mit `null` aufgerufen (für Klassen-Level-
 * Abilities wie `user.create` oder `role.list`).
 *
 * **Wichtig:** PolicyMiddleware prüft die Ability, aber nicht die
 * Authentifizierung selbst. `AuthMiddleware` MUSS vorher laufen, sonst
 * kennt die Policy keinen aktuellen User und gibt konsistent `false`
 * zurück → 403 für anonyme Requests statt 401.
 */
final class PolicyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $ability,
        private readonly ?string $resourceParam = null,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $resource = $this->resolveResource($request);

        try {
            Gate::authorize($this->ability, $resource);
        } catch (AuthorizationException $e) {
            return $this->denied($request, $e);
        }

        return $next($request);
    }

    /**
     * Holt die Resource aus dem Request. Wenn `$resourceParam` gesetzt ist,
     * wird der gleichnamige Route-Parameter als Attribute aus dem Request
     * gelesen. Sonst wird `null` zurückgegeben (Klassen-Level-Ability).
     */
    private function resolveResource(Request $request): mixed
    {
        if ($this->resourceParam === null) {
            return null;
        }
        return $request->attribute($this->resourceParam);
    }

    private function denied(Request $request, AuthorizationException $e): Response
    {
        if ($this->isApiRequest($request)) {
            return Response::json([
                'success' => false,
                'message' => 'Keine Berechtigung',
                'ability' => $e->ability(),
            ], 403);
        }

        if (class_exists(\App\Helpers\Flash::class)) {
            \App\Helpers\Flash::set('error', 'no-permissions');
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        return Response::redirect($base . 'index.php');
    }

    private function isApiRequest(Request $request): bool
    {
        if (str_starts_with($request->path, '/api/')) {
            return true;
        }
        $accept = $request->header('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }
}
