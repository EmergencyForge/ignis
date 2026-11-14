<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sperre für laufende Cron-Jobs (cron-scheduler 0.2): belegt, solange ein
 * Job läuft. Ein Tick oder „Jetzt ausführen“ in dieser Zeit lässt ihn liegen.
 */
class AddIntraCronJobsLockedUntil extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_cron_jobs')
            ->addColumn('locked_until', 'datetime', ['null' => true, 'after' => 'next_run_at'])
            ->update();
    }
}
