<?php

declare(strict_types=1);

/**
 * intraRP: Event-Listener-Map
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

// Modul-Events kommen aus den Plugin-Fragmenten (plugins/*/events.php)
// per PluginLoader::mergeEventMap() dazu.
// Kern-Listener laufen vor denen der Plugins: das Mail-Plugin liest die
// Verknüpfung, die LinkSavedPersonnel gerade gesetzt hat.
return [
    \App\Events\PersonnelSaved::class => [\App\Personnel\LinkSavedPersonnel::class],
    // Kalender ← Anträge: genehmigte Abwesenheiten als Termin. Feuert nur,
    // solange das Antrags-Plugin aktiv ist.
    \Plugin\Forms\Events\FormDecided::class => [\App\Calendar\Listeners\SyncAbsenceOnFormDecided::class],
    \Plugin\Forms\Events\FormDeleted::class => [\App\Calendar\Listeners\RemoveAbsenceOnFormDeleted::class],
];
