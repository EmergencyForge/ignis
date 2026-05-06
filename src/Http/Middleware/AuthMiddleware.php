<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Prüft, ob der User eingeloggt ist.
 *
 * Zwei Betriebsmodi:
 *
 *  1) **Hard-Require** (Default): Fehlende Session → bei HTML-Routes
 *     Redirect zu /login.php, bei API-Routes 401 JSON.
 *
 *  2) **Config-gated**: Wird mit einem Config-Flag parametrisiert (z.B.
 *     `ENOTF_REQUIRE_USER_AUTH`) — greift nur, wenn das Flag `true` ist.
 *     Bei `false` passiert die Middleware transparent durch. Wird für
 *     Module verwendet, deren Auth-Anforderung deploy-seitig konfigurierbar
 *     ist (eNOTF, Fire-Incidents, Wissensdatenbank).
 *
 * Die Unterscheidung HTML vs. API wird heuristisch getroffen:
 *   - Pfad beginnt mit `/api/` → API
 *   - Accept-Header enthält `application/json` → API
 *   - Request-Attribut `force_json` ist gesetzt → API
 * Alles andere bekommt einen Redirect.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        /**
         * Name eines Config-Flags, das diese Middleware aktiviert.
         * `null` (Default) = immer aktiv. String = `defined($flag) && constant($flag)`.
         */
        private readonly ?string $configFlag = null,
        /**
         * Wenn true, invertiert die Flag-Logik: Middleware greift, wenn das
         * Flag `false` ist. Für "public if flag=true"-Szenarien wie
         * `KB_PUBLIC_ACCESS`: die KB-Routes rufen AuthMiddleware mit
         * `configFlag=KB_PUBLIC_ACCESS, invert=true` — Auth wird erzwungen,
         * es sei denn KB ist als public freigeschaltet.
         */
        private readonly bool $invert = false,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        if (!$this->isActive()) {
            return $next($request);
        }

        if (isset($_SESSION['userid'])) {
            return $next($request);
        }

        // Nicht eingeloggt — passend zur Route antworten
        if ($this->isApiRequest($request)) {
            return Response::json(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
        }

        // HTML: Redirect zu login.php mit gemerkter Ziel-URL
        \App\Session\SessionManager::setRedirectUrl($request->server['REQUEST_URI'] ?? $request->path);
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        return Response::redirect($base . 'login');
    }

    private function isActive(): bool
    {
        if ($this->configFlag === null) {
            return true;
        }

        $flagValue = defined($this->configFlag) ? (bool) constant($this->configFlag) : false;
        return $this->invert ? !$flagValue : $flagValue;
    }

    private function isApiRequest(Request $request): bool
    {
        if (str_starts_with($request->path, '/api/')) {
            return true;
        }
        $accept = $request->header('Accept') ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        return (bool) $request->attribute('force_json', false);
    }
}
