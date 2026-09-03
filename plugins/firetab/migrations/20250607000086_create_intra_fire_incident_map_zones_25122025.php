<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Zonen (Polygone) auf der taktischen Einsatzkarte: benannte Flächen mit
 * Farbcode, Punktliste als JSON und Verweis auf Ersteller, Fahrzeug und
 * Bediener.
 */
class CreateIntraFireIncidentMapZones25122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_map_zones')) {
            return;
        }

        $this->table('intra_fire_incident_map_zones', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'Tactical map zones for fire incidents',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Name of the zone'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => 'Optional description of the zone'])
            ->addColumn('points', 'text', ['null' => false, 'comment' => 'JSON array of polygon points'])
            ->addColumn('color', 'string', ['limit' => 20, 'default' => '#dc3545', 'null' => false, 'comment' => 'Color of the zone'])
            ->addColumn('created_by', 'integer', ['null' => true, 'comment' => 'User ID who created the zone'])
            ->addColumn('vehicle_id', 'integer', ['null' => true, 'comment' => 'Vehicle ID that created the zone'])
            ->addColumn('operator_id', 'integer', ['null' => true, 'comment' => 'Operator (person) on the vehicle who created the zone'])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addIndex(['created_by'], ['name' => 'idx_created_by'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addIndex(['operator_id'], ['name' => 'idx_operator'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'fk_map_zone_incident'])
            ->addForeignKey('created_by', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_map_zone_user'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_map_zone_vehicle'])
            ->addForeignKey('operator_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_map_zone_operator'])
            ->create();
    }
}
