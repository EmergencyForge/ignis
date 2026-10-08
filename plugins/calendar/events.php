<?php

/**
 * Kalender: Event-Listener-Zuordnung. Genehmigte Abwesenheitsanträge
 * erscheinen als Termin. Die Events feuert das Plugin Anträge; ist es
 * abgeschaltet, kommen sie nicht und die Zuordnung bleibt folgenlos.
 *
 * Wird per PluginLoader::mergeEventMap() in die Kern-Event-Map gemergt.
 */

return [
    \Plugin\Forms\Events\FormDecided::class => [\Plugin\Calendar\Listeners\SyncAbsenceOnFormDecided::class],
    \Plugin\Forms\Events\FormDeleted::class => [\Plugin\Calendar\Listeners\RemoveAbsenceOnFormDeleted::class],
];
