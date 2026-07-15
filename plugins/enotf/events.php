<?php

/**
 * eNOTF — Event-Listener-Zuordnung.
 *
 * Wird per PluginLoader::mergeEventMap() in die Kern-Event-Map gemergt.
 */

return [
    \Plugin\Enotf\Events\EnotfProtocolReleased::class => [
        \Plugin\Enotf\Listeners\DispatchDiscordWebhookOnEnotfReleased::class,
    ],
    \Plugin\Enotf\Events\EnotfPreregistered::class => [
        \Plugin\Enotf\Listeners\DispatchDiscordWebhookOnEnotfPreregistered::class,
    ],
];
