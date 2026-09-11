<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Kerntabelle des Einsatzprotokolls: ein Datensatz pro Feuerwehr-Einsatz
 * mit Stichwort, Einsatzort, Einsatzleiter, Geschädigten-/Eigentümer-Daten
 * sowie Sichtungs- und Abschluss-Status.
 */
class CreateIntraFireIncidents23122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incidents')) {
            return;
        }

        $this->table('intra_fire_incidents', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('incident_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('location', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('keyword', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('started_at', 'datetime', ['null' => false])
            ->addColumn('leader_id', 'integer', ['null' => true])
            ->addColumn('owner_type', 'enum', ['values' => ['geschaedigter', 'eigentümer', 'halter'], 'null' => true])
            ->addColumn('owner_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('owner_contact', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 0, 'null' => false])
            ->addColumn('finalized', 'boolean', ['default' => 0, 'null' => false])
            ->addColumn('finalized_at', 'timestamp', ['null' => true])
            ->addColumn('finalized_by', 'integer', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_by', 'integer', ['null' => true])
            ->addIndex(['incident_number'], ['unique' => true, 'name' => 'uniq_incident_number'])
            ->addIndex(['started_at'], ['name' => 'idx_started_at'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['leader_id'], ['name' => 'idx_leader'])
            ->addForeignKey('leader_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('finalized_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('updated_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
