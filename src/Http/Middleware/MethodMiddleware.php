<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Prüft, ob die HTTP-Methode einer Liste von erlaubten Methoden entspricht.
 *
 * Normalerweise unnötig, weil Routen im Router bereits mit einer oder
 * mehreren Methoden registriert werden und FastRoute 405 zurückgibt.
 * Diese Middleware ist nur für den Fall, dass ein Handler intern nochmal
 * Unterscheidungen braucht (z.B. ein "multi-method"-Endpoint, der GET
 * anders behandelt als POST und beides erlauben soll).
 */
final class MethodMiddleware implements MiddlewareInterface
{
    /** @var array<int,string> */
    private array $methods;

    /**
     * @param  string|array<int,string>  $methods  z.B. "POST" oder ["GET","POST"]
     */
    public function __construct(string|array $methods)
    {
        if (is_string($methods)) {
            $parts = preg_split('/[\s,]+/', trim($methods)) ?: [];
            $this->methods = array_map('strtoupper', array_values(array_filter($parts)));
        } else {
            $this->methods = array_map('strtoupper', array_values($methods));
        }
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array(strtoupper($request->method), $this->methods, true)) {
            return (new Response(405, 'Method Not Allowed'))
                ->withHeader('Allow', implode(', ', $this->methods));
        }
        return $next($request);
    }
}
