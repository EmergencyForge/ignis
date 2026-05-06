<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;

/**
 * Prüft, ob der eingeloggte User mindestens eine der angegebenen Permissions
 * hat. Setzt Auth voraus — AuthMiddleware MUSS vor dieser laufen, sonst
 * wird bei nicht eingeloggten Usern mit 403 statt 401 geantwortet (weniger
 * informativ).
 *
 * Parametrisierung via Pipeline-Shortstring:
 *     "App\\Http\\Middleware\\PermissionMiddleware:admin"
 *     "App\\Http\\Middleware\\PermissionMiddleware:personnel.edit,personnel.admin"
 *
 * Oder direkt als Instanz:
 *     new PermissionMiddleware('admin')
 *     new PermissionMiddleware(['personnel.edit', 'personnel.admin'])
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var array<int,string> */
    private array $permissions;

    /**
     * @param  string|array<int,string>  $permissions  Einzelne Permission oder Liste (OR-verknüpft)
     */
    public function __construct(string|array $permissions)
    {
        if (is_string($permissions)) {
            // Mehrere Permissions können per Komma oder Leerzeichen getrennt sein
            // (z.B. wenn via Pipeline-Shortstring gereicht).
            $parts = preg_split('/[\s,]+/', trim($permissions)) ?: [];
            $this->permissions = array_values(array_filter($parts));
        } else {
            $this->permissions = array_values($permissions);
        }
    }

    public function process(Request $request, callable $next): Response
    {
        if (!Permissions::check($this->permissions)) {
            if ($this->isApiRequest($request)) {
                return Response::json(['success' => false, 'message' => 'Keine Berechtigung'], 403);
            }

            // HTML: Flash-Message setzen und zur Startseite — konsistent zum
            // Verhalten von Controller::ensure() aus der Stub-Ära.
            if (class_exists(\App\Helpers\Flash::class)) {
                \App\Helpers\Flash::set('error', 'no-permissions');
            }
            $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
            return Response::redirect($base . 'index');
        }

        return $next($request);
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
