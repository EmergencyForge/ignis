<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Aktivitätslog eines Einsatzes: wer hat wann was gemacht (Fahrzeug
 * angemeldet, Status geändert, Sitrep erfasst, ...), optional mit Bezug
 * auf Fahrzeug und Bediener.
 */
class CreateIntraFireIncidentLog24122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_log')) {
            return;
        }

        $this->table('intra_fire_incident_log', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('action_type', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('action_description', 'text', ['null' => false])
            ->addColumn('vehicle_id', 'integer', ['null' => true])
            ->addColumn('operator_id', 'integer', ['null' => true])
            ->addColumn('created_by', 'integer', ['null' => false])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addIndex(['operator_id'], ['name' => 'idx_operator'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
