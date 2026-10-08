<?php

declare(strict_types=1);

namespace Plugin\Firetab\Listeners;

use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordWebhookJob;
use Plugin\Firetab\Events\FireProtocolReleased;

/**
 * Listener: dispatcht einen SendDiscordWebhookJob, sobald ein
 * FireProtocolReleased-Event gefeuert wird.
 */
final class DispatchDiscordWebhookOnFireReleased
{
    public function __construct(
        private readonly JobDispatcher $jobs,
    ) {}

    public function handle(FireProtocolReleased $event): void
    {
        $this->jobs->dispatch(
            // Nur was die Meldung zeigt, nicht der ganze Einsatz.
            new SendDiscordWebhookJob('fire_released', array_intersect_key($event->incidentData, array_flip(['id', 'incident_number', 'location', 'keyword', 'started_at', 'leader_name'])))
        );
    }
}
