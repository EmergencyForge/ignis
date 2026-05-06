<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\DiscordWebhook;
use App\Logging\Logger;

/**
 * Job: Sendet eine Discord-Webhook-Benachrichtigung asynchron.
 *
 * Wird von Controllern dispatched, die vorher synchron `DiscordWebhook`
 * aufgerufen haben — der HTTP-Request an den Discord-Server wird jetzt
 * vom Queue-Worker bearbeitet, statt den User-Request zu blockieren.
 *
 * Unterstützte Typen (entsprechen den existierenden Methoden auf
 * `DiscordWebhook`):
 *   - 'enotf_released'         → notifyEnotfProtocolReleased
 *   - 'fire_released'          → notifyFireProtocolReleased
 *   - 'enotf_preregistration'  → notifyEnotfPreregistration
 *
 * Beispiel:
 *
 *     app(JobDispatcher::class)->dispatch(
 *         new SendDiscordWebhookJob('enotf_released', $protocolData)
 *     );
 */
final class SendDiscordWebhookJob extends Job
{
    public int $tries = 3;
    public string $queue = 'notifications';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $type,
        private readonly array $data,
    ) {}

    public function handle(): void
    {
        $webhook = app(DiscordWebhook::class);

        $success = match ($this->type) {
            'enotf_released'        => $webhook->notifyEnotfProtocolReleased($this->data),
            'fire_released'         => $webhook->notifyFireProtocolReleased($this->data),
            'enotf_preregistration' => $webhook->notifyEnotfPreregistration($this->data),
            default                 => throw new \InvalidArgumentException("Unbekannter Webhook-Typ: {$this->type}"),
        };

        if (!$success) {
            // DiscordWebhook gibt false zurück wenn der Webhook nicht
            // konfiguriert ist oder der HTTP-Call fehlschlägt. Wir werfen
            // eine Exception damit der Job in den Retry-Flow geht.
            throw new \RuntimeException("DiscordWebhook lieferte false für Typ '{$this->type}'");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Logger::error('SendDiscordWebhookJob: Final failure after retries', [
            'type'     => $this->type,
            'error'    => $exception->getMessage(),
            'data_key' => array_keys($this->data),
        ]);
    }
}
