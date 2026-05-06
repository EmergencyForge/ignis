<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FireProtocolReleased;
use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordWebhookJob;

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
            new SendDiscordWebhookJob('fire_released', $event->incidentData)
        );
    }
}
