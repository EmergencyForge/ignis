<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erlaubt NULL in den User-Referenzspalten der Einsatzprotokoll-Tabellen,
 * damit System-Einträge und Aktionen nicht angemeldeter Fahrzeuge ohne
 * User-Bezug gespeichert werden können.
 */
class AlterIntraFireIncidents20250125AllowNull extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_fire_incidents')
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->changeColumn('updated_by', 'integer', ['null' => true])
            ->changeColumn('finalized_by', 'integer', ['null' => true])
            ->update();

        $this->table('intra_fire_incident_vehicles')
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->update();

        $this->table('intra_fire_incident_sitreps')
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->update();

        $this->table('intra_fire_incident_log')
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->update();
    }

    public function down(): void
    {
        // Vor dieser Migration war nur intra_fire_incident_log.created_by
        // NOT NULL (siehe CREATE vom 24122025) — die übrigen Spalten waren
        // schon immer nullable und bleiben unangetastet.
        $this->table('intra_fire_incident_log')
            ->changeColumn('created_by', 'integer', ['null' => false])
            ->update();
    }
}
