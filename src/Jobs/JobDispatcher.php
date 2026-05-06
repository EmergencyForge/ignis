<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Logging\Logger;
use Illuminate\Queue\QueueManager;

/**
 * Zentraler Dispatcher für Hintergrund-Jobs.
 *
 * Wrappt `Illuminate\Queue\QueueManager` und versteckt die Implementations-
 * details (DB-Driver, Queue-Name, Serialisierung). Application-Code nutzt
 * nur:
 *
 *     app(JobDispatcher::class)->dispatch(new MyJob(...));
 *
 * Bei Queue-Fehlern fällt der Dispatcher in einen Sync-Modus zurück und
 * führt den Job direkt aus — das ist defensive Programmierung für den
 * Fall, dass die Queue-Infrastruktur noch nicht deployed ist oder die
 * DB-Tabelle fehlt. Der Call-Site merkt davon nichts.
 */
final class JobDispatcher
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {}

    /**
     * Legt einen Job in die Queue. Wird beim nächsten Worker-Run ausgeführt.
     */
    public function dispatch(Job $job): void
    {
        try {
            $connection = $this->queueManager->connection();

            $payload = [
                'job'  => SerializedJob::class . '@handle',
                'data' => [
                    'class'      => get_class($job),
                    'serialized' => serialize($job),
                ],
            ];

            if ($job->delay > 0) {
                $connection->later($job->delay, $payload, null, $job->queue);
            } else {
                $connection->push($payload, '', $job->queue);
            }
        } catch (\Throwable $e) {
            Logger::warning('JobDispatcher: Queue unavailable, running job synchronously', [
                'job'   => get_class($job),
                'error' => $e->getMessage(),
            ]);
            $this->dispatchSync($job);
        }
    }

    /**
     * Führt den Job sofort aus, ohne Queue. Nützlich als Fallback und für
     * Tests. Exceptions werden geloggt und an `failed()` weitergereicht.
     */
    public function dispatchSync(Job $job): void
    {
        try {
            $job->handle();
        } catch (\Throwable $e) {
            Logger::error('JobDispatcher: Sync job failed', [
                'job'   => get_class($job),
                'error' => $e->getMessage(),
            ]);
            try {
                $job->failed($e);
            } catch (\Throwable $failedException) {
                Logger::error('JobDispatcher: failed()-handler crashed', [
                    'job'   => get_class($job),
                    'error' => $failedException->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
