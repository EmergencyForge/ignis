<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrtenbuch für Fahrzeuge: Fahrten mit Datum, Abfahrt/Ankunft, Kilometern,
 * Grund und Fahrer. Einträge kommen aus eNOTF, FireTab oder werden manuell
 * im Admin-Bereich angelegt (source). vehicle_identifier bleibt auch dann
 * erhalten, wenn das verknüpfte Fahrzeug gelöscht wird.
 */
class CreateIntraFahrtenbuch04042026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fahrtenbuch')) {
            return;
        }

        $this->table('intra_fahrtenbuch', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('vehicle_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('vehicle_identifier', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('datum', 'date', ['null' => false])
            ->addColumn('abfahrt', 'time', ['null' => false])
            ->addColumn('ankunft', 'time', ['null' => true, 'default' => null])
            ->addColumn('stationierungsort', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('kilometer', 'decimal', ['precision' => 8, 'scale' => 1, 'null' => true, 'default' => null])
            ->addColumn('grund', 'text', ['null' => true, 'default' => null])
            ->addColumn('fahrttyp', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('fahrer_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('source', 'enum', [
                'values'  => ['enotf', 'firetab', 'admin'],
                'default' => 'admin',
                'null'    => false,
            ])
            ->addColumn('created_by', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', [
                'null'    => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['created_by'], ['name' => 'created_by'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_fahrtenbuch_vehicle'])
            ->addIndex(['datum'], ['name' => 'idx_fahrtenbuch_datum'])
            ->addIndex(['fahrttyp'], ['name' => 'idx_fahrtenbuch_fahrttyp'])
            ->addIndex(['fahrer_name'], ['name' => 'idx_fahrtenbuch_fahrer'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
