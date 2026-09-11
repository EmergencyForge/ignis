<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrzeuge, die an einem Einsatz beteiligt sind — entweder verknüpft mit
 * einem Fahrzeug aus intra_fahrzeuge oder freitextlich erfasst (z. B.
 * Fahrzeuge fremder Organisationen).
 */
class CreateIntraFireIncidentVehicles23122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_vehicles')) {
            return;
        }

        $this->table('intra_fire_incident_vehicles', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('vehicle_id', 'integer', ['null' => true])
            ->addColumn('vehicle_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('vehicle_identifier', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('from_other_org', 'boolean', ['default' => 0, 'null' => false])
            ->addColumn('radio_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
