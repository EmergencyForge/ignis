<?php

declare(strict_types=1);

namespace Plugin\Enotf\Events;

use App\Events\Event;

/**
 * Wird gefeuert, wenn eine eNOTF-Voranmeldung erfolgt ist
 * (Klinik-Schnittstelle: RTW meldet Patient vor Ankunft an).
 *
 * Listener:
 *   - DispatchDiscordWebhookOnEnotfPreregistered → Discord-Benachrichtigung
 *     (damit Klinik-Personal über Voranmeldungen informiert wird)
 */
final class EnotfPreregistered extends Event
{
    /**
     * @param  array<string, mixed>  $preregData
     */
    public function __construct(
        public readonly array $preregData,
    ) {}
}
