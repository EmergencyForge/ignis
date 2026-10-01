<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Jobs\Job;
use App\Jobs\JobDispatcher;
use EmergencyForge\Cron\Handler\JobHandlerInterface;
use EmergencyForge\Cron\JobResult;

/**
 * Dispatcht einen bestehenden Queue-Job (FQCN in `$handler`) in die
 * DB-Queue. Der Scheduler gilt als erfolgreich, sobald der Job eingereiht
 * ist — die eigentliche Ausführung übernimmt dann der Queue-Worker.
 *
 * Eingereiht wird über den JobDispatcher, also nur Unterklassen von
 * App\Jobs\Job: ein anderes Objekt legte Illuminate als
 * CallQueuedHandler ab, und den kann der Worker hier nicht ausführen.
 *
 * `config` kann Konstruktor-Argumente via `args` mitgeben.
 */
final class JobDispatchHandler implements JobHandlerInterface
{
    public function __construct(private readonly JobDispatcher $dispatcher)
    {
    }

    public function run(string $handler, array $config, int $timeoutSeconds): JobResult
    {
        $startedAt = microtime(true);

        if (!class_exists($handler)) {
            return JobResult::failed(0, "Job-Klasse nicht gefunden: {$handler}");
        }
        if (!is_subclass_of($handler, Job::class)) {
            return JobResult::failed(0, "Keine Job-Klasse (App\Jobs\Job): {$handler}");
        }

        try {
            $args = (array) ($config['args'] ?? []);
            /** @var Job $jobInstance */
            $jobInstance = new $handler(...$args);
            $jobInstance->queue = (string) ($config['queue'] ?? 'default');
            $this->dispatcher->dispatch($jobInstance);
        } catch (\Throwable $e) {
            return JobResult::failed(
                (int) round((microtime(true) - $startedAt) * 1000),
                'Dispatch fehlgeschlagen: ' . $e->getMessage()
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        return JobResult::success($durationMs, 'Job in Queue eingereiht.');
    }
}
