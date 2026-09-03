<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Trägt eine Weiterleitung aus einem Controller bis zum Router.
 *
 * Controller::redirect() hat vorher `header('Location: …'); exit;` gemacht.
 * Damit lief nach dem Aufruf nichts mehr, aber auch nichts Nützliches:
 * kein Haken des Routers sah die Antwort (afterDispatch, siehe
 * RouterFactory), und ein Feature-Test starb mit dem Prozess. Jetzt wirft
 * redirect() diese Exception; Router::buildHandlerCallable() fängt sie
 * direkt am Handler und macht daraus Response::redirect(), noch bevor die
 * Middlewares die Antwort auf dem Rückweg sehen. Für den Controller-Code
 * ändert sich nichts: nach redirect() geht es nicht weiter.
 *
 * Absichtlich von \Exception abgeleitet und nicht von \RuntimeException,
 * damit ein `catch (\RuntimeException)` um einen Speichervorgang die
 * Weiterleitung nicht schluckt. Ein `catch (\Throwable)` um einen
 * redirect()-Aufruf gibt es in den Controllern nicht (geprüft bei I7).
 */
final class RedirectException extends \Exception
{
    public function __construct(
        public readonly string $location,
        public readonly int $status = 302,
    ) {
        parent::__construct('Redirect to ' . $location, $status);
    }

    public function toResponse(): Response
    {
        return Response::redirect($this->location, $this->status);
    }
}
