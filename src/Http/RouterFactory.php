<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Requests\FormRequest;
use EmergencyForge\Http\Pipeline;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Http\Router;
use Psr\Container\ContainerInterface;

/**
 * Baut den Router mit allem, was ignis daran anhängt. Einzige Stelle, an
 * der ein Router entsteht: der Container (config/container.php, Produktion)
 * und Tests\FeatureTestCase rufen beide hierher, damit ein Test-Router
 * dieselben Haken trägt wie der echte.
 */
final class RouterFactory
{
    /**
     * @param bool $cache  false baut den Dispatcher bei jedem Aufruf frisch;
     *                     Tests nutzen das, damit das Cache-File aus dem
     *                     Betrieb ihre Routen nicht verfälscht.
     */
    public static function create(ContainerInterface $container, Pipeline $pipeline, bool $cache = true): Router
    {
        $router = new Router(
            $container,
            $pipeline,
            cacheFile: $cache ? dirname(__DIR__, 2) . '/storage/cache/routes.php' : null,
        );

        // CSRF vor dem Stack jeder Route, statt an jeder einzeln. Solange
        // der Schutz pro Route angemeldet werden musste, trugen ihn elf von
        // vierundsechzig schreibenden Routen — weder die Rollenverwaltung
        // noch der Auslöser des Systemupdates gehörten dazu. Die Ausnahmen
        // stehen in der Middleware.
        $router->middleware(new Middleware\CsrfMiddleware());

        // Die 404-Seite gehört dem Produkt, nicht dem Paket. ErrorPage
        // liefert für /api/-Pfade JSON statt HTML und fällt auf Plain-Text
        // zurück, wenn das Template fehlt - eine kaputte Installation soll
        // einen 404 liefern und nicht still einen 500.
        $router->setNotFoundHandler(
            static fn (Request $request): Response => ErrorPage::notFound($request->path),
        );

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
