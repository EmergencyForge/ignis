<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * EMD-Status pro Einsatzfahrzeug: aktueller Status (0-9, N, #, C) und
 * Zeitpunkt der letzten Aktualisierung.
 */
class AlterFireIncidentVehicles27122025AddStatus extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_vehicles');
        if ($table->hasColumn('current_status')) {
            return;
        }

        $table
            ->addColumn('current_status', 'string', ['limit' => 10, 'null' => true, 'comment' => 'Aktueller EMD Status (0-9, N, #, C)', 'after' => 'radio_name'])
            ->addColumn('status_updated_at', 'timestamp', ['null' => true, 'comment' => 'Zeitpunkt der letzten Status-Aktualisierung', 'after' => 'current_status'])
            ->update();
    }
}
