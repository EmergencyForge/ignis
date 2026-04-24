<?php

declare(strict_types=1);

namespace App\Console\Commands;

use PDO;
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

    public function __construct(private readonly PDO $pdo)
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
        $sql = "DELETE FROM intra_failed_jobs WHERE failed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :days DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => self::DB_RETENTION_DAYS]);
        return $stmt->rowCount();
    }

    private function cleanCronRuns(): int
    {
        // Alte Runs pro Job löschen, aber die neuesten N pro Job behalten,
        // damit das Admin-UI zumindest für inaktive Jobs Historie anzeigt.
        $total = 0;

        $jobStmt = $this->pdo->query("SELECT id FROM intra_cron_jobs");
        $jobs = $jobStmt !== false ? $jobStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $select = $this->pdo->prepare(
            "SELECT id FROM intra_cron_runs
              WHERE job_id = :jid
                AND started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :days DAY)
              ORDER BY started_at DESC
              LIMIT 100000 OFFSET :keep"
        );
        $delete = $this->pdo->prepare("DELETE FROM intra_cron_runs WHERE id = :id");

        foreach ($jobs as $jobId) {
            $select->bindValue(':jid',  (int) $jobId, PDO::PARAM_INT);
            $select->bindValue(':days', self::DB_RETENTION_DAYS, PDO::PARAM_INT);
            $select->bindValue(':keep', self::CRON_MIN_RUNS_KEEP, PDO::PARAM_INT);
            $select->execute();
            foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $runId) {
                $delete->execute([':id' => (int) $runId]);
                $total++;
            }
        }
        return $total;
    }
}
