<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Marker auf der taktischen Einsatzkarte: Position in Prozent (0-100),
 * Marker-Typ (fire, vehicle, person, water, danger, command, staging,
 * other) und Verweis auf Ersteller bzw. erstellendes Fahrzeug.
 */
class CreateIntraFireIncidentMapMarkers25122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_map_markers')) {
            return;
        }

        $this->table('intra_fire_incident_map_markers', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'Tactical map markers for fire incidents',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('marker_type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Type of marker: fire, vehicle, person, water, danger, command, staging, other'])
            ->addColumn('pos_x', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false, 'comment' => 'X position as percentage (0-100)'])
            ->addColumn('pos_y', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => false, 'comment' => 'Y position as percentage (0-100)'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => 'Optional description of the marker'])
            ->addColumn('created_by', 'integer', ['null' => true, 'comment' => 'User ID who created the marker'])
            ->addColumn('vehicle_id', 'integer', ['null' => true, 'comment' => 'Vehicle ID that created the marker'])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addIndex(['created_by'], ['name' => 'idx_created_by'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addIndex(['marker_type'], ['name' => 'idx_marker_type'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'fk_map_marker_incident'])
            ->addForeignKey('created_by', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_map_marker_user'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_map_marker_vehicle'])
            ->create();
    }
}
