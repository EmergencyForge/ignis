<?php

declare(strict_types=1);

/**
 * intraRP — Event-Listener-Map
 *
 * Zentrales Mapping Event → Listener. Wird vom EventServiceRegistrar
 * beim Container-Build gelesen und registriert alle Listener beim
 * Illuminate-Dispatcher.
 *
 * Struktur:
 *
 *     Event-Klasse::class => [
 *         Listener1::class,
 *         Listener2::class,
 *         ...
 *     ]
 *
 * Listener werden in der hier angegebenen Reihenfolge aufgerufen. Jeder
 * Listener ist eine Klasse mit einer `handle(EventClass $event): void`-
 * Methode und wird via DI-Container instanziiert (Constructor-Injection
 * funktioniert automatisch).
 *
 * Neue Listener hinzufügen: hier eintragen, fertig. Kein Bootstrap-Code
 * anfassen.
 */

use App\Events\EnotfPreregistered;
use App\Events\EnotfProtocolReleased;
use App\Listeners\DispatchDiscordWebhookOnEnotfPreregistered;
use App\Listeners\DispatchDiscordWebhookOnEnotfReleased;

return [
    EnotfProtocolReleased::class => [
        DispatchDiscordWebhookOnEnotfReleased::class,
    ],
    EnotfPreregistered::class => [
        DispatchDiscordWebhookOnEnotfPreregistered::class,
    ],
];
