<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Atemschutzüberwachung (ASÜ) pro Einsatz: ein Datensatz je Überwacher mit
 * den Trupp-Daten als JSON-Blob. Pro Einsatz und Überwacher genau ein
 * Datensatz (Unique-Key).
 */
class CreateIntraFireIncidentAsu20250101 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_incident_asu')) {
            return;
        }

        $this->table('intra_fire_incident_asu', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('incident_id', 'integer', ['null' => false])
            ->addColumn('supervisor', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('mission_location', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('mission_date', 'date', ['null' => true])
            ->addColumn('timestamp', 'datetime', ['null' => true])
            ->addColumn('data', 'json', ['null' => false])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['incident_id', 'supervisor'], ['unique' => true, 'name' => 'unique_incident_supervisor'])
            ->addIndex(['incident_id'], ['name' => 'idx_incident'])
            ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
