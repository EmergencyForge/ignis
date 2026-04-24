<?php

declare(strict_types=1);

namespace App\Cron;

use App\Cron\JobHandler\ConsoleHandler;
use App\Cron\JobHandler\JobDispatchHandler;
use App\Cron\JobHandler\JobHandlerInterface;
use App\Cron\JobHandler\WebhookHandler;
use App\Logging\Logger;
use Cron\CronExpression;
use PDO;
use Psr\Container\ContainerInterface;

/**
 * Zentrale Scheduler-Logik für das hauseigene Cron-System.
 *
 * Ein `tick()`-Aufruf:
 *   1. Findet alle Jobs mit `active = 1 AND next_run_at <= NOW()`
 *   2. Sperrt den Job per Optimistic-Lock (UPDATE ... WHERE last_run_at = ?)
 *   3. Führt den Job via zugehörigem Handler aus
 *   4. Persistiert Run-Log, aktualisiert Job-Status, berechnet next_run_at
 *   5. Pausiert Jobs die zu oft fehlschlagen (Fail-Counter >= 5)
 *
 * Fail-Counter wird bei Erfolg zurückgesetzt. Geplantes Deaktivieren passiert
 * ohne Datenverlust — Admin kann den Job im UI wieder aktivieren.
 */
final class CronScheduler
{
    private const DEFAULT_TIMEOUT   = 55;
    private const MAX_RUNS_PER_TICK = 10;
    private const FAIL_LIMIT        = 5;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Führt alle fälligen Jobs aus. Rückgabewert: Anzahl tatsächlich gelaufener
     * Jobs (nicht Skips oder Locks).
     */
    public function tick(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, identifier, handler_type, handler, schedule, config,
                    last_run_at, fail_count
               FROM intra_cron_jobs
              WHERE active = 1
                AND (next_run_at IS NULL OR next_run_at <= UTC_TIMESTAMP())
              ORDER BY next_run_at ASC, id ASC
              LIMIT :lim"
        );
        $stmt->bindValue(':lim', self::MAX_RUNS_PER_TICK, PDO::PARAM_INT);
        $stmt->execute();
        $dueJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $executed = 0;
        foreach ($dueJobs as $row) {
            if ($this->acquireLock((int) $row['id'], $row['last_run_at'])) {
                $this->runJob($row);
                $executed++;
            }
        }
        return $executed;
    }

    /**
     * Lock per UPDATE ... WHERE — verhindert Double-Execute bei parallelen
     * Triggern (Piggyback-Middleware + Cron-Endpoint).
     */
    private function acquireLock(int $jobId, ?string $previousLastRunAt): bool
    {
        $sql = $previousLastRunAt === null
            ? "UPDATE intra_cron_jobs SET last_run_at = UTC_TIMESTAMP() WHERE id = :id AND last_run_at IS NULL"
            : "UPDATE intra_cron_jobs SET last_run_at = UTC_TIMESTAMP() WHERE id = :id AND last_run_at = :prev";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $jobId, PDO::PARAM_INT);
        if ($previousLastRunAt !== null) {
            $stmt->bindValue(':prev', $previousLastRunAt);
        }
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function runJob(array $row): JobResult
    {
        $jobId   = (int) $row['id'];
        $handler = $this->resolveHandler((string) $row['handler_type']);
        $config  = $this->decodeConfig($row['config'] ?? null);
        $timeout = (int) ($config['timeout'] ?? self::DEFAULT_TIMEOUT);

        $runId = $this->startRunLog($jobId);

        try {
            $result = $handler === null
                ? JobResult::failed(0, 'Unbekannter handler_type: ' . $row['handler_type'])
                : $handler->run((string) $row['handler'], $config, $timeout);
        } catch (\Throwable $e) {
            Logger::error('CronScheduler: handler threw', [
                'job_id' => $jobId,
                'error'  => $e->getMessage(),
            ]);
            $result = JobResult::failed(0, $e->getMessage());
        }

        $this->finishRunLog($runId, $result);
        $this->updateJob((int) $row['id'], (int) $row['fail_count'], (string) $row['schedule'], $result);

        return $result;
    }

    private function resolveHandler(string $type): ?JobHandlerInterface
    {
        return match ($type) {
            'console' => $this->container->get(ConsoleHandler::class),
            'webhook' => $this->container->get(WebhookHandler::class),
            'job'     => $this->container->get(JobDispatchHandler::class),
            default   => null,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeConfig(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function startRunLog(int $jobId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO intra_cron_runs (job_id, started_at, status)
             VALUES (:id, UTC_TIMESTAMP(), 'running')"
        );
        $stmt->execute([':id' => $jobId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function finishRunLog(int $runId, JobResult $result): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE intra_cron_runs
                SET finished_at = UTC_TIMESTAMP(),
                    status      = :status,
                    duration_ms = :dur,
                    output      = :out
              WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $result->status,
            ':dur'    => $result->durationMs,
            ':out'    => $result->output,
            ':id'     => $runId,
        ]);
    }

    private function updateJob(int $jobId, int $currentFails, string $schedule, JobResult $result): void
    {
        $nextRunAt = $this->computeNextRun($schedule);
        $newFails  = $result->isSuccess() ? 0 : $currentFails + 1;
        $pause     = $newFails >= self::FAIL_LIMIT;

        $stmt = $this->pdo->prepare(
            "UPDATE intra_cron_jobs
                SET last_status      = :status,
                    last_duration_ms = :dur,
                    last_output      = :out,
                    next_run_at      = :next,
                    fail_count       = :fails,
                    active           = CASE WHEN :pause = 1 THEN 0 ELSE active END
              WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $result->status,
            ':dur'    => $result->durationMs,
            ':out'    => $result->output,
            ':next'   => $nextRunAt,
            ':fails'  => $newFails,
            ':pause'  => $pause ? 1 : 0,
            ':id'     => $jobId,
        ]);

        if ($pause) {
            Logger::warning('CronScheduler: job paused due to fail-limit', [
                'job_id' => $jobId,
                'fails'  => $newFails,
            ]);
        }
    }

    private function computeNextRun(string $schedule): string
    {
        $utcNow = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        try {
            $expression = new CronExpression($schedule);
            return $expression->getNextRunDate($utcNow, 0, false, 'UTC')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            Logger::error('CronScheduler: invalid schedule', [
                'schedule' => $schedule,
                'error'    => $e->getMessage(),
            ]);
            return $utcNow->modify('+1 hour')->format('Y-m-d H:i:s');
        }
    }

    /**
     * Einmalige Sofort-Ausführung (z.B. vom Admin-UI "Run Now"-Button).
     * Respektiert den Fail-Limit-Pause-Mechanismus nicht.
     *
     * @return array<string,mixed>
     */
    public function runJobById(int $jobId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, identifier, handler_type, handler, schedule, config,
                    last_run_at, fail_count
               FROM intra_cron_jobs WHERE id = :id"
        );
        $stmt->execute([':id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Job nicht gefunden'];
        }

        $result = $this->runJob($row);
        return [
            'ok'          => $result->isSuccess(),
            'status'      => $result->status,
            'duration_ms' => $result->durationMs,
            'output'      => $result->output,
        ];
    }
}
