<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Requests\FormRequest;
use Psr\Container\ContainerInterface;

/**
 * Baut den Router mit allem, was ignis daran anhängt. Einzige Stelle, an
 * der ein Router entsteht: der Container (config/container.php, Produktion)
 * und Tests\FeatureTestCase rufen beide hierher, damit ein Test-Router
 * dieselben Haken trägt wie der echte.
 */
final class RouterFactory
{
    public static function create(ContainerInterface $container, Pipeline $pipeline, bool $cache = true): Router
    {
        $router = new Router($container, $pipeline, enableCache: $cache);

        // dispatch() ist der einzige Ort, an dem sowohl ein echter
        // Produktions-Request als auch ein simulierter Test-Request eindeutig
        // beginnt, siehe FormRequest::resetOldInputCache().
        $router->beforeDispatch(static function (): void {
            FormRequest::resetOldInputCache();
        });

        // Fragment-Aufrufer (assets/js/ui/drawer-form.js) posten per fetch.
        // Ein fetch folgt Redirects selbst und würde dabei die Zielseite
        // samt ihrer Flash-Meldung verbrauchen; deshalb bekommt er statt
        // des Redirects eine leere 200-Antwort mit dem Ziel im Header und
        // entscheidet selbst, ob er das Fragment neu lädt oder die Seite
        // wechselt.
        $router->afterDispatch(static function (Request $request, Response $response): Response {
            $location = $response->headers['Location'] ?? null;
            if ($location === null || $response->status < 300 || $response->status > 399) {
                return $response;
            }
            if ($request->header('X-Requested-With') !== 'fragment') {
                return $response;
            }
            return new Response(200, '', ['X-Ignis-Location' => $location, 'Cache-Control' => 'no-store']);
        });

        return $router;
    }
}
