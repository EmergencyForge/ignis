<?php

declare(strict_types=1);

namespace App\Cron;

use EmergencyForge\Cron\Job;
use EmergencyForge\Cron\JobResult;
use EmergencyForge\Cron\JobStore;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Der Bestand hinter dem Scheduler: `intra_cron_jobs` für die geplanten
 * Jobs, `intra_cron_runs` für die Läufe. emergencyforge/cron-scheduler
 * kennt beide Tabellen nicht, hier stehen sie.
 *
 * Alle Zeitangaben in UTC, wie die Spalten sie tragen.
 */
final class EloquentJobStore implements JobStore
{
    private const COLUMNS = [
        'id', 'identifier', 'handler_type', 'handler', 'schedule', 'config',
        'last_run_at', 'fail_count',
    ];

    /**
     * @return list<Job>
     */
    public function dueJobs(int $limit): array
    {
        $rows = Capsule::table('intra_cron_jobs')
            ->where('active', 1)
            ->where(static function ($query): void {
                $query->whereNull('next_run_at')
                    ->orWhereRaw('next_run_at <= UTC_TIMESTAMP()');
            })
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(self::COLUMNS);

        return array_values(array_map(fn ($row): Job => $this->toJob($row), $rows->all()));
    }

    public function find(int $jobId): ?Job
    {
        $row = Capsule::table('intra_cron_jobs')->where('id', $jobId)->first(self::COLUMNS);

        return $row === null ? null : $this->toJob($row);
    }

    /**
     * Optimistic Lock: der Schreibvorgang greift nur, solange in
     * `last_run_at` noch der Wert steht, den dieser Job beim Lesen hatte.
     * Wer zu spät kommt, bekommt 0 betroffene Zeilen — so läuft ein Job
     * auch dann nur einmal, wenn Piggyback-Tick und Cron-Endpunkt
     * gleichzeitig anklopfen.
     */
    public function acquireLock(Job $job): bool
    {
        $query = Capsule::table('intra_cron_jobs')->where('id', $job->id);

        if ($job->lastRunAt === null) {
            $query->whereNull('last_run_at');
        } else {
            $query->where('last_run_at', $job->lastRunAt);
        }

        return $query->update(['last_run_at' => Capsule::connection()->raw('UTC_TIMESTAMP()')]) === 1;
    }

    public function startRun(int $jobId): int
    {
        return (int) Capsule::table('intra_cron_runs')->insertGetId([
            'job_id'     => $jobId,
            'started_at' => Capsule::connection()->raw('UTC_TIMESTAMP()'),
            'status'     => 'running',
        ]);
    }

    public function finishRun(int $runId, JobResult $result): void
    {
        Capsule::table('intra_cron_runs')
            ->where('id', $runId)
            ->update([
                'finished_at' => Capsule::connection()->raw('UTC_TIMESTAMP()'),
                'status'      => $result->status,
                'duration_ms' => $result->durationMs,
                'output'      => $result->output,
            ]);
    }

    public function saveOutcome(int $jobId, JobResult $result, string $nextRunAt, int $failCount, bool $paused): void
    {
        $update = [
            'last_status'      => $result->status,
            'last_duration_ms' => $result->durationMs,
            'last_output'      => $result->output,
            'next_run_at'      => $nextRunAt,
            'fail_count'       => $failCount,
        ];
        if ($paused) {
            $update['active'] = 0;
        }

        Capsule::table('intra_cron_jobs')->where('id', $jobId)->update($update);
    }

    private function toJob(object $row): Job
    {
        return new Job(
            id:          (int) $row->id,
            identifier:  (string) $row->identifier,
            handlerType: (string) $row->handler_type,
            handler:     (string) $row->handler,
            schedule:    (string) $row->schedule,
            config:      Job::decodeConfig($row->config === null ? null : (string) $row->config),
            lastRunAt:   $row->last_run_at === null ? null : (string) $row->last_run_at,
            failCount:   (int) $row->fail_count,
        );
    }
}
