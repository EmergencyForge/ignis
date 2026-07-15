<?php

declare(strict_types=1);

namespace Plugin\Enotf\Listeners;

use Plugin\Enotf\Events\EnotfProtocolReleased;
use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordWebhookJob;

/**
 * Listener: dispatcht einen SendDiscordWebhookJob, sobald ein
 * EnotfProtocolReleased-Event gefeuert wird.
 *
 * Der Listener entkoppelt das "Protokoll wurde freigegeben"-Ereignis
 * von der konkreten Discord-Integration — weitere Listener (z.B. für
 * Audit-Log, Federation) können unabhängig hinzugefügt werden, ohne
 * dass der Call-Site (Controller/LegacyApi) etwas davon mitbekommt.
 */
final class DispatchDiscordWebhookOnEnotfReleased
{
    public function __construct(
        private readonly JobDispatcher $jobs,
    ) {}

    public function handle(EnotfProtocolReleased $event): void
    {
        $this->jobs->dispatch(
            new SendDiscordWebhookJob('enotf_released', $event->protocolData)
        );
    }
}
