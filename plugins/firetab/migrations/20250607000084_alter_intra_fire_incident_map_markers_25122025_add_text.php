<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ergänzt Text-Beschriftung, Name und Typ der taktischen Zeichen für
 * Karten-Marker. Jede Spalte wird einzeln geprüft, da Teilstände aus
 * früheren Versionen existieren können.
 */
class AlterIntraFireIncidentMapMarkers25122025AddText extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_map_markers');
        $changed = false;

        if (!$table->hasColumn('text')) {
            $table->addColumn('text', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Text-Beschriftung', 'after' => 'symbol']);
            $changed = true;
        }
        if (!$table->hasColumn('name')) {
            $table->addColumn('name', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Name', 'after' => 'text']);
            $changed = true;
        }
        if (!$table->hasColumn('typ')) {
            $table->addColumn('typ', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Typ (einsatz, geplant, etc.)', 'after' => 'name']);
            $changed = true;
        }

        if ($changed) {
            $table->update();
        }
    }
}
