<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Minimal-Interface für intraRP-Middleware. Bewusst kein PSR-15, um die
 * Abhängigkeit auf PSR-7 zu vermeiden (siehe Request.php).
 *
 * Eine Middleware bekommt den Request und eine `$next`-Closure. Sie darf:
 *   - den Request modifizieren (withAttribute) und `$next($request)` aufrufen
 *   - eine Response direkt zurückgeben (short-circuit, z.B. 401/403)
 *   - die Response von `$next($request)` nachbearbeiten (z.B. Header setzen)
 *
 * Signatur der $next-Closure:
 *     fn(Request $request): Response
 */
interface MiddlewareInterface
{
    /**
     * @param  callable(Request): Response  $next
     */
    public function process(Request $request, callable $next): Response;
}
