<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Queue-Infrastruktur (Phase 4.1).
 *
 * Erzeugt die zwei Tabellen, die `illuminate/queue` mit dem DB-Driver
 * braucht:
 *
 *   - `intra_jobs`         — wartende und in Bearbeitung befindliche Jobs
 *   - `intra_failed_jobs`  — Jobs die final gescheitert sind (nach Retries)
 *
 * Schema ist bewusst kompatibel zum Laravel-Default — wir nutzen die
 * Standard-DatabaseQueue-Implementierung, die dieses Schema erwartet.
 * Der intraRP-Prefix `intra_` ist aus Konsistenz zu anderen Tabellen
 * gewählt; der Queue-Manager bekommt die Tabellennamen explizit via
 * Config, kein Magic.
 */
class CreateIntraJobsAndFailedJobs extends AbstractMigration
{
    public function change(): void
    {
        // ── intra_jobs ───────────────────────────────────────────────
        if (!$this->hasTable('intra_jobs')) {
            $this->table('intra_jobs', ['id' => 'id', 'signed' => false])
                ->addColumn('queue',         'string',  ['limit' => 191])
                ->addColumn('payload',       'text',    ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
                ->addColumn('attempts',      'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'signed' => false])
                ->addColumn('reserved_at',   'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => true])
                ->addColumn('available_at',  'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR, 'signed' => false])
                ->addColumn('created_at',    'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR, 'signed' => false])
                ->addIndex(['queue', 'reserved_at'], ['name' => 'intra_jobs_queue_reserved_at_index'])
                ->create();
        }

        // ── intra_failed_jobs ────────────────────────────────────────
        if (!$this->hasTable('intra_failed_jobs')) {
            $this->table('intra_failed_jobs', ['id' => 'id', 'signed' => false])
                ->addColumn('uuid',       'string',   ['limit' => 36])
                ->addColumn('connection', 'text')
                ->addColumn('queue',      'text')
                ->addColumn('payload',    'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
                ->addColumn('exception',  'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
                ->addColumn('failed_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['uuid'], ['unique' => true, 'name' => 'intra_failed_jobs_uuid_unique'])
                ->create();
        }
    }
}
