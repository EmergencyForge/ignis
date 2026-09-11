<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ergänzt das Symbol-Feld der taktischen Zeichen für Karten-Marker.
 */
class AlterIntraFireIncidentMapMarkers25122025AddSymbol extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_map_markers');
        if ($table->hasColumn('symbol')) {
            return;
        }

        $table
            ->addColumn('symbol', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Symbol', 'after' => 'einheit'])
            ->update();
    }
}
