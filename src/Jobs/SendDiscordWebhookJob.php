<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\DiscordWebhook;
use App\Integrations\DiscordWebhookException;
use App\Logging\Logger;

/**
 * Job: Sendet eine Discord-Webhook-Benachrichtigung asynchron.
 *
 * Wird von Controllern dispatched, die vorher synchron `DiscordWebhook`
 * aufgerufen haben. Der HTTP-Request an den Discord-Server wird jetzt
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

        // Ohne eingetragene URL liefert DiscordWebhook false: dann ist
        // nichts zu tun, kein Fehler. Netzfehler, Rate-Limit und Störungen
        // bei Discord gehen in den Retry der Queue; eine Nachricht, die
        // Discord ablehnt (4xx), würde es beim nächsten Versuch genauso.
        try {
            match ($this->type) {
                'enotf_released'        => $webhook->notifyEnotfProtocolReleased($this->data),
                'fire_released'         => $webhook->notifyFireProtocolReleased($this->data),
                'enotf_preregistration' => $webhook->notifyEnotfPreregistration($this->data),
                default                 => throw new \InvalidArgumentException("Unbekannter Webhook-Typ: {$this->type}"),
            };
        } catch (DiscordWebhookException $e) {
            if ($e->retryable()) {
                throw $e;
            }
            Logger::error('SendDiscordWebhookJob: Discord hat abgelehnt', [
                'type'  => $this->type,
                'error' => $e->getMessage(),
            ]);
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
