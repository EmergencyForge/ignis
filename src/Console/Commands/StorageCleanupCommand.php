<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php storage:cleanup`
 *
 * Räumt regelmäßige Hinterlassenschaften auf:
 * - `storage/temp/update_*`  — Updater-Temp-Verzeichnisse älter als 24h
 * - `intra_failed_jobs`      — Einträge älter als 30 Tage
 * - `intra_cron_runs`        — Run-Historie älter als 30 Tage (pro Job aber mindestens 10 letzte behalten)
 */
#[AsCommand(
    name: 'storage:cleanup',
    description: 'Räumt alte Temp-Verzeichnisse, Failed-Jobs und Cron-Run-Historie auf',
)]
final class StorageCleanupCommand extends Command
{
    private const TEMP_MAX_AGE_SECONDS = 24 * 3600;
    private const DB_RETENTION_DAYS    = 30;
    private const CRON_MIN_RUNS_KEEP   = 10;

    public function __construct()
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appRoot = dirname(__DIR__, 3);

        $removedDirs = $this->cleanTempDirs($appRoot);
        $output->writeln("  Temp-Verzeichnisse entfernt: <info>{$removedDirs}</info>");

        $removedFailed = $this->cleanFailedJobs();
        $output->writeln("  Alte failed_jobs gelöscht:  <info>{$removedFailed}</info>");

        $removedRuns = $this->cleanCronRuns();
        $output->writeln("  Alte cron_runs gelöscht:    <info>{$removedRuns}</info>");

        return Command::SUCCESS;
    }

    private function cleanTempDirs(string $appRoot): int
    {
        $tempBase = $appRoot . '/storage/temp';
        if (!is_dir($tempBase)) {
            return 0;
        }
        $now = time();
        $count = 0;
        $dirs = glob($tempBase . '/update_*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime === false || ($now - $mtime) < self::TEMP_MAX_AGE_SECONDS) {
                continue;
            }
            $this->recursiveDelete($dir);
            $count++;
        }
        return $count;
    }

    private function recursiveDelete(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $this->recursiveDelete($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }

    private function cleanFailedJobs(): int
    {
        return Capsule::table('intra_failed_jobs')
            ->whereRaw('failed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [self::DB_RETENTION_DAYS])
            ->delete();
    }

    private function cleanCronRuns(): int
    {
        // Alte Runs pro Job löschen, aber die neuesten N pro Job behalten,
        // damit das Admin-UI zumindest für inaktive Jobs Historie anzeigt.
        $total = 0;

        $jobs = Capsule::table('intra_cron_jobs')->pluck('id')->all();

        foreach ($jobs as $jobId) {
            $runIds = Capsule::table('intra_cron_runs')
                ->where('job_id', (int) $jobId)
                ->whereRaw('started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [self::DB_RETENTION_DAYS])
                ->orderByDesc('started_at')
                ->offset(self::CRON_MIN_RUNS_KEEP)
                ->limit(100000)
                ->pluck('id')
                ->all();

            foreach ($runIds as $runId) {
                $total += Capsule::table('intra_cron_runs')->where('id', (int) $runId)->delete();
            }
        }
        return $total;
    }
}
