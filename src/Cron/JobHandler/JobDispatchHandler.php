<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Cron\JobResult;
use Illuminate\Queue\QueueManager;

/**
 * Dispatcht einen bestehenden Queue-Job (FQCN in `$handler`) in die
 * DB-Queue. Der Scheduler gilt als erfolgreich, sobald der Job eingereiht
 * ist — die eigentliche Ausführung übernimmt dann der Queue-Worker.
 *
 * `config` kann Konstruktor-Argumente via `args` mitgeben.
 */
final class JobDispatchHandler implements JobHandlerInterface
{
    public function __construct(private readonly QueueManager $queueManager)
    {
    }

    public function run(string $handler, array $config, int $timeoutSeconds): JobResult
    {
        $startedAt = microtime(true);

        if (!class_exists($handler)) {
            return JobResult::failed(0, "Job-Klasse nicht gefunden: {$handler}");
        }

        try {
            $args = (array) ($config['args'] ?? []);
            /** @var object $jobInstance */
            $jobInstance = new $handler(...$args);
            $connection = $this->queueManager->connection();
            $queueName  = (string) ($config['queue'] ?? 'default');
            $connection->push($jobInstance, '', $queueName);
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
