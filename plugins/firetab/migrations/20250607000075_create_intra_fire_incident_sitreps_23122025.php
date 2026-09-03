<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Lagemeldungen (Sitreps) zu einem Einsatz — Freitext mit Meldezeitpunkt,
 * optional zugeordnet zu einem meldenden Fahrzeug.
 */
class CreateIntraFireIncidentSitreps23122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_sitreps')) {
            return;
        }

        $this->table('intra_fire_incident_sitreps', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('report_time', 'datetime', ['null' => false])
            ->addColumn('text', 'text', ['null' => false])
            ->addColumn('vehicle_radio_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('vehicle_id', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addIndex(['report_time'], ['name' => 'idx_report_time'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
