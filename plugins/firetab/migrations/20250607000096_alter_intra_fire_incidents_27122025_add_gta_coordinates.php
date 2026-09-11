<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * GTA-Koordinaten des Einsatzortes (X/Y) inkl. kombiniertem Index für
 * Umkreis-Abfragen.
 */
class AlterIntraFireIncidents27122025AddGtaCoordinates extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incidents');
        if ($table->hasColumn('location_x')) {
            return;
        }

        $table
            ->addColumn('location_x', 'decimal', ['precision' => 14, 'scale' => 9, 'null' => true, 'comment' => 'GTA X coordinate of incident location', 'after' => 'location'])
            ->addColumn('location_y', 'decimal', ['precision' => 14, 'scale' => 9, 'null' => true, 'comment' => 'GTA Y coordinate of incident location', 'after' => 'location_x'])
            ->addIndex(['location_x', 'location_y'], ['name' => 'idx_location_coords'])
            ->update();
    }
}
