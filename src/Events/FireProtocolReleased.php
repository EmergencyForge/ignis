<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Wird gefeuert, wenn ein Fire-Incident-Protokoll final freigegeben
 * wurde (QM-Sichtung abgeschlossen). Trigger kommt aus dem
 * EinsatzController::actionQmRelease().
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
