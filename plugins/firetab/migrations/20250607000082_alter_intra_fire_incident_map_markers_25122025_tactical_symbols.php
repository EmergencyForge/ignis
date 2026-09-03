<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Taktische Zeichen für Karten-Marker: Grundzeichen, Organisation,
 * Fachaufgabe und Einheit als frei kombinierbare Bausteine.
 */
class AlterIntraFireIncidentMapMarkers25122025TacticalSymbols extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_map_markers');
        if ($table->hasColumn('grundzeichen')) {
            return;
        }

        $table
            ->addColumn('grundzeichen', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Grundzeichen', 'after' => 'marker_type'])
            ->addColumn('organisation', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Organisation', 'after' => 'grundzeichen'])
            ->addColumn('fachaufgabe', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Fachaufgabe', 'after' => 'organisation'])
            ->addColumn('einheit', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Taktisches Zeichen: Einheit', 'after' => 'fachaufgabe'])
            ->update();
    }
}
