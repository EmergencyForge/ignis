<?php

declare(strict_types=1);

namespace App\Events;

use App\Logging\Logger;
use Illuminate\Events\Dispatcher as IlluminateDispatcher;

/**
 * Zentraler Event-Dispatcher für intraRP.
 *
 * Thin-Wrapper um `Illuminate\Events\Dispatcher` — wir nutzen die bereits
 * vorhandene Illuminate-Infrastruktur (kommt mit Eloquent) statt ein
 * separates Package zu installieren. Das Interface ist bewusst minimal:
 *
 *     app(EventDispatcher::class)->fire(new EnotfProtocolReleased($data));
 *
 * Listener-Registration passiert über den EventServiceRegistrar, der beim
 * Container-Build aufgerufen wird. Call-Sites selbst kennen nur die
 * `fire()`-Methode und die Event-Klassen — nichts anderes.
 *
 * Warum nicht direkt `Illuminate\Events\Dispatcher` injecten? Weil der
 * einen ganzen Haufen Methoden hat, die wir nicht brauchen (subscribe,
 * forget, until, shouldBroadcast, ...). Ein kleiner eigener Wrapper macht
 * die Call-Sites lesbarer und isoliert uns von API-Änderungen in Illuminate.
 */
final class EventDispatcher
{
    public function __construct(
        private readonly IlluminateDispatcher $events,
    ) {}

    /**
     * Feuert ein Event an alle registrierten Listener.
     *
     * Listener-Exceptions werden gefangen und geloggt — ein fehlerhafter
     * Listener soll niemals die anderen Listener oder den User-Request
     * blockieren. Das ist eine bewusste Design-Entscheidung für
     * Side-Effect-Robustness.
     */
    public function fire(Event $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (\Throwable $e) {
            Logger::error('EventDispatcher: listener exception', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Direkter Zugriff auf den Underlying-Dispatcher — nur für
     * Framework-Code (z.B. Listener-Registrierung im EventServiceRegistrar).
     * Application-Code sollte das nicht nutzen.
     */
    public function illuminate(): IlluminateDispatcher
    {
        return $this->events;
    }
}
