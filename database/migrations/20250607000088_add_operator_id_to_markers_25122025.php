<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt operator_id zu intra_fire_incident_map_markers hinzu: die Person auf
 * dem Fahrzeug, die den Marker gesetzt hat — inklusive Index und Foreign Key
 * auf intra_mitarbeiter (ON DELETE SET NULL, ON UPDATE CASCADE).
 */
class AddOperatorIdToMarkers25122025 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_map_markers');
        if ($table->hasColumn('operator_id')) {
            return;
        }

        $table
            ->addColumn('operator_id', 'integer', [
                'null'    => true,
                'comment' => 'Operator (person) on the vehicle who created the marker',
                'after'   => 'vehicle_id',
            ])
            ->addIndex(['operator_id'], ['name' => 'idx_operator'])
            ->addForeignKey('operator_id', 'intra_mitarbeiter', 'id', [
                'delete'     => 'SET_NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_map_marker_operator',
            ])
            ->update();
    }
}
