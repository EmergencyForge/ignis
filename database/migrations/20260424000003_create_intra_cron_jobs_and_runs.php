<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cron-Infrastruktur für Managed-Hosting-Umgebungen ohne Unix-Cron.
 *
 * - `intra_cron_jobs` registriert alle geplanten Tasks (Console-Command,
 *   Queue-Job-Dispatch, Webhook) mit Cron-Expression-Schedule.
 * - `intra_cron_runs` loggt jede Ausführung mit Status, Dauer und Output —
 *   dient dem Admin-UI als History.
 */
class CreateIntraCronJobsAndRuns extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_cron_jobs')) {
            $this->table('intra_cron_jobs', ['id' => 'id', 'signed' => false])
                ->addColumn('identifier',       'string',    ['limit' => 120])
                ->addColumn('name',             'string',    ['limit' => 191])
                ->addColumn('description',      'text',      ['null' => true])
                ->addColumn('handler_type',     'enum',      ['values' => ['console', 'job', 'webhook']])
                ->addColumn('handler',          'string',    ['limit' => 500])
                ->addColumn('schedule',         'string',    ['limit' => 100])
                ->addColumn('config',           'text',      ['null' => true])
                ->addColumn('last_run_at',      'timestamp', ['null' => true])
                ->addColumn('next_run_at',      'timestamp', ['null' => true])
                ->addColumn('last_status',      'string',    ['limit' => 20, 'null' => true])
                ->addColumn('last_duration_ms', 'integer',   ['null' => true, 'signed' => false])
                ->addColumn('last_output',      'text',      ['null' => true])
                ->addColumn('fail_count',       'integer',   ['default' => 0, 'signed' => false])
                ->addColumn('active',           'boolean',   ['default' => true])
                ->addColumn('is_builtin',       'boolean',   ['default' => false])
                ->addColumn('created_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['identifier'], ['unique' => true, 'name' => 'intra_cron_jobs_identifier_unique'])
                ->addIndex(['active', 'next_run_at'], ['name' => 'intra_cron_jobs_due_index'])
                ->create();
        }

        if (!$this->hasTable('intra_cron_runs')) {
            $this->table('intra_cron_runs', ['id' => 'id', 'signed' => false])
                ->addColumn('job_id',      'integer',   ['signed' => false])
                ->addColumn('started_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('finished_at', 'timestamp', ['null' => true])
                ->addColumn('status',      'string',    ['limit' => 20])
                ->addColumn('duration_ms', 'integer',   ['null' => true, 'signed' => false])
                ->addColumn('output',      'text',      ['null' => true])
                ->addIndex(['job_id', 'started_at'], ['name' => 'intra_cron_runs_job_time_index'])
                ->addForeignKey('job_id', 'intra_cron_jobs', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}
