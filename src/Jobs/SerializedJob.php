<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Logging\Logger;
use Illuminate\Contracts\Queue\Job as IlluminateJobContract;

/**
 * Adapter zwischen Illuminate's Job-Payload-Format und unseren `Job`-
 * Basisklassen.
 *
 * Illuminate speichert jeden Job-Payload in der Queue mit einem `job`-
 * Pointer im Format `Class@method`. Wenn der Worker einen Job verarbeitet,
 * instanziiert er die Klasse und ruft die Methode auf, wobei er den
 * Payload-Daten und die Job-Instanz als Parameter reicht.
 *
 * Diese Klasse ist dieser Pointer — `SerializedJob::handle(IlluminateJob, $data)`.
 * Sie deserialisiert den tatsächlichen Application-Job aus `$data['serialized']`,
 * führt dessen `handle()`-Methode aus, und markiert im Fehlerfall den
 * Queue-Eintrag korrekt als failed/released.
 */
final class SerializedJob
{
    /**
     * @param  array{class: class-string<Job>, serialized: string}  $data
     */
    public function handle(IlluminateJobContract $illuminateJob, array $data): void
    {
        try {
            /** @var Job $job */
            $job = unserialize($data['serialized']);

            if (!($job instanceof Job)) {
                Logger::error('SerializedJob: payload is not a Job instance', [
                    'class' => $data['class'] ?? 'unknown',
                ]);
                $illuminateJob->fail(new \RuntimeException('Ungültiger Job-Payload'));
                return;
            }

            $job->handle();
            $illuminateJob->delete();
        } catch (\Throwable $e) {
            Logger::error('SerializedJob: job execution failed', [
                'class'    => $data['class'] ?? 'unknown',
                'error'    => $e->getMessage(),
                'attempts' => $illuminateJob->attempts(),
            ]);

            // Max-Attempts-Check: wenn Retries erschöpft, als failed markieren
            // (damit es in intra_failed_jobs landet), sonst release für Retry
            $maxTries = isset($job) && $job instanceof Job ? $job->tries : 3;

            if ($illuminateJob->attempts() >= $maxTries) {
                try {
                    if (isset($job) && $job instanceof Job) {
                        $job->failed($e);
                    }
                } catch (\Throwable $failedHandlerException) {
                    Logger::error('SerializedJob: failed()-handler crashed', [
                        'error' => $failedHandlerException->getMessage(),
                    ]);
                }
                $illuminateJob->fail($e);
            } else {
                // Release mit Backoff: 30s, 60s, 120s
                $backoff = min(120, 30 * (2 ** ($illuminateJob->attempts() - 1)));
                $illuminateJob->release($backoff);
            }
        }
    }
}
