<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Marker-Basis für intraRP-Domain-Events.
 *
 * Ein Event ist ein unveränderliches Daten-Objekt, das ein Ereignis
 * beschreibt, das **bereits passiert ist** (Past Tense — z.B.
 * `EnotfProtocolReleased`, `UserDeactivated`). Events werden vom
 * `EventDispatcher` an alle registrierten Listener gereicht.
 *
 * Listener entscheiden dann, was zu tun ist — Discord-Webhook dispatchen,
 * Audit-Log schreiben, Federation-Sync anstoßen etc. Der Emitter weiß
 * nichts von den Listenern; das ist der Kern der Entkopplung.
 *
 * Konvention:
 *   - Events sind `final class ... extends Event`
 *   - Konstruktor nimmt nur Daten (keine Services), alle Properties
 *     sind `public readonly`
 *   - Klassenname im Past Tense
 *
 * Event-Listeners werden in `config/events.php` registriert.
 */
abstract class Event
{
    /**
     * Wird vom Dispatcher für Illuminate-Kompatibilität genutzt — Illuminate's
     * `Dispatcher::dispatch()` ruft intern `get_class($event)` als Event-Namen.
     * Wir müssen nichts tun, das funktioniert out-of-the-box.
     */
}
