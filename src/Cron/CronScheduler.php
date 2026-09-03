<?php

declare(strict_types=1);

namespace App\Cron;

use App\Cron\JobHandler\ConsoleHandler;
use App\Cron\JobHandler\JobDispatchHandler;
use App\Cron\JobHandler\JobHandlerInterface;
use App\Cron\JobHandler\WebhookHandler;
use App\Logging\Logger;
use Cron\CronExpression;
use Illuminate\Database\Capsule\Manager as Capsule;
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
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Führt alle fälligen Jobs aus. Rückgabewert: Anzahl tatsächlich gelaufener
     * Jobs (nicht Skips oder Locks).
     */
    public function tick(): int
    {
        $dueJobs = Capsule::table('intra_cron_jobs')
            ->where('active', 1)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhereRaw('next_run_at <= UTC_TIMESTAMP()');
            })
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->limit(self::MAX_RUNS_PER_TICK)
            ->get([
                'id', 'identifier', 'handler_type', 'handler', 'schedule', 'config',
                'last_run_at', 'fail_count',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

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
        $query = Capsule::table('intra_cron_jobs')->where('id', $jobId);

        if ($previousLastRunAt === null) {
            $query->whereNull('last_run_at');
        } else {
            $query->where('last_run_at', $previousLastRunAt);
        }

        $affected = $query->update(['last_run_at' => Capsule::raw('UTC_TIMESTAMP()')]);
        return $affected === 1;
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
        return Capsule::table('intra_cron_runs')->insertGetId([
            'job_id'     => $jobId,
            'started_at' => Capsule::raw('UTC_TIMESTAMP()'),
            'status'     => 'running',
        ]);
    }

    private function finishRunLog(int $runId, JobResult $result): void
    {
        Capsule::table('intra_cron_runs')
            ->where('id', $runId)
            ->update([
                'finished_at' => Capsule::raw('UTC_TIMESTAMP()'),
                'status'      => $result->status,
                'duration_ms' => $result->durationMs,
                'output'      => $result->output,
            ]);
    }

    private function updateJob(int $jobId, int $currentFails, string $schedule, JobResult $result): void
    {
        $nextRunAt = $this->computeNextRun($schedule);
        $newFails  = $result->isSuccess() ? 0 : $currentFails + 1;
        $pause     = $newFails >= self::FAIL_LIMIT;

        $update = [
            'last_status'      => $result->status,
            'last_duration_ms' => $result->durationMs,
            'last_output'      => $result->output,
            'next_run_at'      => $nextRunAt,
            'fail_count'       => $newFails,
        ];
        if ($pause) {
            $update['active'] = 0;
        }

        Capsule::table('intra_cron_jobs')
            ->where('id', $jobId)
            ->update($update);

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
        $row = Capsule::table('intra_cron_jobs')
            ->where('id', $jobId)
            ->first([
                'id', 'identifier', 'handler_type', 'handler', 'schedule', 'config',
                'last_run_at', 'fail_count',
            ]);
        if (!$row) {
            return ['ok' => false, 'error' => 'Job nicht gefunden'];
        }

        $result = $this->runJob((array) $row);
        return [
            'ok'          => $result->isSuccess(),
            'status'      => $result->status,
            'duration_ms' => $result->durationMs,
            'output'      => $result->output,
        ];
    }
}
