<?php

declare(strict_types=1);

namespace Plugin\Firetab\Events;

use App\Events\Event;

/**
 * Wird gefeuert, wenn ein Fire-Incident-Protokoll final freigegeben
 * wurde (QM-Sichtung abgeschlossen). Trigger kommt aus dem
 * FiretabController::actionQmRelease().
 *
 * Listener:
 *   - DispatchDiscordWebhookOnFireReleased → sendet Discord-Benachrichtigung
 */
final class FireProtocolReleased extends Event
{
    /**
     * @param  array<string, mixed>  $incidentData
     */
    public function __construct(
        public readonly array $incidentData,
    ) {}
}
