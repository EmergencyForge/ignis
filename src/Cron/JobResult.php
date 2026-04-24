<?php

declare(strict_types=1);

namespace App\Cron;

/**
 * Ergebnis einer Cron-Job-Ausführung.
 *
 * Wird vom jeweiligen Handler zurückgegeben und vom Scheduler persistiert
 * (Status, Dauer, Output in `intra_cron_runs` und aggregiert auf
 * `intra_cron_jobs`).
 */
final class JobResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        public readonly string $status,
        public readonly int $durationMs,
        public readonly string $output = '',
    ) {
    }

    public static function success(int $durationMs, string $output = ''): self
    {
        return new self(self::STATUS_SUCCESS, $durationMs, $output);
    }

    public static function failed(int $durationMs, string $output = ''): self
    {
        return new self(self::STATUS_FAILED, $durationMs, $output);
    }

    public static function skipped(string $reason = ''): self
    {
        return new self(self::STATUS_SKIPPED, 0, $reason);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
