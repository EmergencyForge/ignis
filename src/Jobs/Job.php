<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Basis-Klasse für alle intraRP-Jobs.
 *
 * Wird als serialisierbarer Payload in die Queue gelegt und später vom
 * Worker wieder aufgeweckt. Konkrete Jobs erben hiervon und implementieren
 * `handle()`, wo die eigentliche Arbeit passiert.
 *
 * Dependencies werden beim `handle()`-Aufruf aus dem DI-Container gezogen —
 * der Konstruktor darf NUR serialisierbare Daten (primitives, Arrays,
 * DateTime) enthalten, keine PDO-Instanzen, Logger oder ähnliches, weil
 * der Job nach `serialize()` in die DB geschrieben wird und beim Worker-
 * Run neu aufgebaut wird.
 *
 * Beispiel:
 *
 *     class SendDiscordWebhook extends Job
 *     {
 *         public function __construct(
 *             private readonly string $webhookUrl,
 *             private readonly array  $payload,
 *         ) {}
 *
 *         public function handle(): void
 *         {
 *             $client = app(DiscordWebhook::class);
 *             $client->send($this->webhookUrl, $this->payload);
 *         }
 *     }
 *
 *     // Dispatching:
 *     app(JobDispatcher::class)->dispatch(new SendDiscordWebhook($url, $payload));
 */
abstract class Job
{
    /**
     * Maximale Anzahl von Versuchen, bevor der Job als gescheitert markiert
     * wird. Kann von konkreten Jobs überschrieben werden.
     */
    public int $tries = 3;

    /**
     * Queue-Name — Default "default", kann für Prioritäts-Queues überschrieben
     * werden (z.B. "high", "low", "notifications").
     */
    public string $queue = 'default';

    /**
     * Delay in Sekunden, bevor der Job nach dem Dispatch ausführbar wird.
     * Default 0 = sofort beim nächsten Worker-Run.
     */
    public int $delay = 0;

    /**
     * Hauptmethode — wird vom Worker aufgerufen. Muss implementiert werden.
     */
    abstract public function handle(): void;

    /**
     * Wird aufgerufen, wenn alle Retries ausgeschöpft sind und der Job
     * final gescheitert ist. Default: nichts. Konkrete Jobs können das
     * überschreiben, z.B. um eine Admin-Notification zu senden.
     */
    public function failed(\Throwable $exception): void
    {
        // Default no-op
    }
}
