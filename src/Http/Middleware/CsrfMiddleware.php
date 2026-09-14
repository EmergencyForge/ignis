<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ErrorPage;
use App\Security\CsrfProtection;
use EmergencyForge\Http\Middleware\MiddlewareInterface;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;

/**
 * Erzwingt einen gültigen CSRF-Token für jede schreibende Anfrage.
 *
 * Hängt global am Router (`public/index.php`) und nicht mehr an einzelnen
 * Routen. Der Grund steht in der Ausnahmeliste weiter unten: solange der
 * Schutz pro Route angemeldet werden musste, trugen ihn elf von
 * vierundsechzig schreibenden Routen — darunter weder die Rollenverwaltung
 * noch der Auslöser des Systemupdates. Was überall gelten muss, darf nicht
 * davon abhängen, dass jemand daran denkt.
 *
 * Token-Quellen, in dieser Reihenfolge:
 *   1. JSON-Body `csrf_token`
 *   2. POST-Parameter `csrf_token`
 *   3. Header `X-CSRF-Token`
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Pfade ohne CSRF-Prüfung.
     *
     * Der Schutz sichert eine Sitzung gegen fremde Seiten ab, die im Namen
     * des angemeldeten Browsers schreiben. Wo gar keine Sitzung im Spiel
     * ist, sichert er nichts und sperrt nur aus:
     *
     *   - Die FiveM-Endpunkte sprechen Maschine zu Maschine und weisen sich
     *     über `ApiKeyMiddleware` mit einem API-Key aus. Ein Browser kommt
     *     dort nicht an.
     *
     * `/login` steht bewusst nicht hier: das Anmeldeformular bekommt seinen
     * Token wie jedes andere, und die Sitzung dafür gibt es auch ohne
     * Anmeldung.
     *
     * @var array<int,string>
     */
    private const EXEMPT = [
        '/api/character/identify',
        '/api/emd/sync',
        '/api/emd-sync.php',
    ];

    public function process(Request $request, callable $next): Response
    {
        if (!in_array(strtoupper($request->method), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        if (in_array($request->path, self::EXEMPT, true)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if ($token === null || !CsrfProtection::validateToken($token)) {
            return ErrorPage::forbidden(
                'Das Formular ist abgelaufen. Lade die Seite neu und versuche es noch einmal.',
                $request->path,
            );
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
