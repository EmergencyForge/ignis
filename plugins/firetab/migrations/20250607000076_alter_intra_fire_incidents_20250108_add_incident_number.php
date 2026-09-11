<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rüstet die Einsatznummer für Installationen nach, deren
 * intra_fire_incidents noch ohne incident_number angelegt wurde. Neuere
 * CREATE-Versionen enthalten die Spalte bereits — dann passiert hier nichts.
 */
class AlterIntraFireIncidents20250108AddIncidentNumber extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incidents');
        if ($table->hasColumn('incident_number')) {
            return;
        }

        $table
            ->addColumn('incident_number', 'string', ['limit' => 50, 'null' => true, 'after' => 'id'])
            ->addIndex(['incident_number'], ['unique' => true, 'name' => 'uniq_incident_number'])
            ->update();
    }
}
