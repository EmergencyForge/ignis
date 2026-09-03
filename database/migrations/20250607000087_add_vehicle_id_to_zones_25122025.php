<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt vehicle_id zu intra_fire_incident_map_zones hinzu, damit Zonen einem
 * Fahrzeug zugeordnet werden können — inklusive Index und Foreign Key auf
 * intra_fahrzeuge (ON DELETE SET NULL, ON UPDATE CASCADE).
 *
 * Auf Installationen, deren Zones-Tabelle die Spalte bereits mitbringt,
 * passiert nichts — Index und Constraint existieren dort ebenfalls schon aus
 * dem CREATE TABLE.
 */
class AddVehicleIdToZones25122025 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_map_zones');
        if ($table->hasColumn('vehicle_id')) {
            return;
        }

        $table
            ->addColumn('vehicle_id', 'integer', [
                'null'    => true,
                'comment' => 'Vehicle ID that created the zone',
                'after'   => 'created_by',
            ])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', [
                'delete'     => 'SET_NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_map_zone_vehicle',
            ])
            ->update();
    }
}
