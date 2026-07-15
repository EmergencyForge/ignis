<?php

/**
 * fireTab — Event-Listener-Zuordnung.
 *
 * Wird per PluginLoader::mergeEventMap() in die Kern-Event-Map gemergt.
 */

return [
    \Plugin\Firetab\Events\FireProtocolReleased::class => [
        \Plugin\Firetab\Listeners\DispatchDiscordWebhookOnFireReleased::class,
    ],
];
