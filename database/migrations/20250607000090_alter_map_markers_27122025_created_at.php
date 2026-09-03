<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Stellt intra_fire_incident_map_markers.created_at von DATETIME auf
 * TIMESTAMP mit DEFAULT CURRENT_TIMESTAMP um, damit der Erstellzeitpunkt
 * automatisch gesetzt wird.
 */
class AlterMapMarkers27122025CreatedAt extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_fire_incident_map_markers')
            ->changeColumn('created_at', 'timestamp', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Timestamp when the marker was created',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('intra_fire_incident_map_markers')
            ->changeColumn('created_at', 'datetime', ['null' => false])
            ->update();
    }
}
