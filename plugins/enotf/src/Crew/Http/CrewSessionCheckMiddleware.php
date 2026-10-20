<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Http;

use EmergencyForge\Http\Middleware\MiddlewareInterface;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Enotf\Crew\Support\CrewSessionGuard;
use Plugin\Enotf\Helpers\EnotfUrl;

/**
 * Prüft vor jeder eNOTF-Anfrage, ob die Crew-Anmeldung in der DB noch
 * gilt (CrewSessionGuard). Ist sie beendet:
 *
 *   - MODE_PAGE:  Weiterleitung auf die Abmelde-Seite mit Grund
 *   - MODE_API:   401 { "code": "session_ended", "grund": "…" }, außer
 *                 die Anfrage hat ein ignis-Konto, dann entscheidet der
 *                 Controller über die Rechte des Kontos
 *   - MODE_ENTRY: Login, Sperre, Abmelden: nur aufräumen, die Seite läuft
 *
 * Lesende API-Anfragen (Abgleiche, Polls) verlängern die Session nicht,
 * Seitenaufrufe und schreibende Anfragen schon.
 */
final class CrewSessionCheckMiddleware implements MiddlewareInterface
{
    public const MODE_PAGE  = 'page';
    public const MODE_API   = 'api';
    public const MODE_ENTRY = 'entry';

    public function __construct(private readonly string $mode = self::MODE_PAGE)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $write = strtoupper($request->method) !== 'GET';
        $grund = CrewSessionGuard::check(touch: $this->mode === self::MODE_PAGE || $write);

        if ($grund === null || $this->mode === self::MODE_ENTRY) {
            return $next($request);
        }

        if ($this->mode === self::MODE_API) {
            if (!empty($_SESSION['userid'])) {
                return $next($request);
            }

            return Response::json([
                'ok'      => false,
                'success' => false,
                'code'    => 'session_ended',
                'grund'   => $grund,
                'error'   => CrewSessionGuard::message($grund) ?? 'Nicht angemeldet',
            ], 401);
        }

        return Response::redirect(EnotfUrl::page('loggedout', ['grund' => $grund]));
    }
}
