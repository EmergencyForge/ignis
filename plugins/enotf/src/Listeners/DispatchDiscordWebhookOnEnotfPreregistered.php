<?php

declare(strict_types=1);

namespace Plugin\Enotf\Listeners;

use Plugin\Enotf\Events\EnotfPreregistered;
use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordWebhookJob;

/**
 * Listener: dispatcht einen SendDiscordWebhookJob, sobald eine
 * eNOTF-Voranmeldung erfolgt.
 */
final class DispatchDiscordWebhookOnEnotfPreregistered
{
    public function __construct(
        private readonly JobDispatcher $jobs,
    ) {}

    public function handle(EnotfPreregistered $event): void
    {
        $this->jobs->dispatch(
            new SendDiscordWebhookJob('enotf_preregistration', $event->preregData)
        );
    }
}
