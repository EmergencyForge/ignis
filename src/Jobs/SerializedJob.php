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
            // Security: Class-Whitelist für unserialize() — verhindert
            // Object-Injection-Angriffe falls jemals jemand beliebige Daten
            // in die intra_jobs-Tabelle schreiben sollte (SQLi etc.).
            // Nur Klassen unterhalb App\Jobs\ und bekannte Primitives/Arrays
            // werden deserialisiert; alles andere wird zu __PHP_Incomplete_Class
            // und fällt beim `instanceof Job`-Check unten raus.
            $declaredClass = $data['class'] ?? '';
            if (!is_string($declaredClass) || !str_starts_with($declaredClass, 'App\\Jobs\\')) {
                Logger::error('SerializedJob: rejected non-App\\Jobs class', [
                    'class' => $declaredClass,
                ]);
                $illuminateJob->fail(new \RuntimeException('Ungültige Job-Klasse'));
                return;
            }

            /** @var Job $job */
            $job = unserialize($data['serialized'], [
                'allowed_classes' => $this->allowedJobClasses($declaredClass),
            ]);

            if (!($job instanceof Job)) {
                Logger::error('SerializedJob: payload is not a Job instance', [
                    'class' => $declaredClass,
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

    /**
     * Liefert die Liste von Klassen, die `unserialize()` beim Deserialisieren
     * eines Jobs tatsächlich als echte Objekte rekonstruieren darf. Alle
     * anderen Klassen werden zu `__PHP_Incomplete_Class` und sind damit
     * harmlos — `handle()` und Magic-Methods werden nicht aufgerufen.
     *
     * Wir erlauben die konkrete Job-Klasse selbst plus alle bekannten
     * App\Jobs\* Subklassen. DateTime/DateTimeZone werden auch erlaubt,
     * weil Jobs oft DateTimes als Payload haben.
     *
     * @return array<int, class-string>
     */
    private function allowedJobClasses(string $jobClass): array
    {
        return [
            $jobClass,
            \App\Jobs\Job::class,
            \App\Jobs\SendDiscordWebhookJob::class,
            \App\Jobs\SendNotificationJob::class,
            \DateTime::class,
            \DateTimeImmutable::class,
            \DateTimeZone::class,
            \DateInterval::class,
        ];
    }
}
