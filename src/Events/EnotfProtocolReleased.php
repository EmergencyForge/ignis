<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Wird gefeuert, wenn ein eNOTF-Protokoll final freigegeben wurde
 * (QM-Sichtung abgeschlossen, Freigeber gesetzt).
 *
 * Listener:
 *   - DispatchDiscordWebhookOnEnotfReleased → sendet Discord-Benachrichtigung
 *
 * Weitere denkbare Listener (noch nicht implementiert):
 *   - AuditLog: Freigabe-Eintrag in intra_audit_log
 *   - Federation-Sync: Protokoll an Hub-Server pushen
 */
final class EnotfProtocolReleased extends Event
{
    /**
     * @param  array<string, mixed>  $protocolData  Die vollständige intra_edivi-Row
     */
    public function __construct(
        public readonly array $protocolData,
    ) {}
}
