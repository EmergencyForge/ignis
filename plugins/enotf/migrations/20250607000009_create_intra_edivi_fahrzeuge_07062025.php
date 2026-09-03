<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrzeugstamm für das eDIVI-Protokoll: Rettungsmittel mit Funkkennung,
 * Anzeigename, Fahrzeugtyp und Kennzeichnung als arztbesetztes Fahrzeug.
 * Sortierung über `priority`, Deaktivierung über `active`.
 */
class CreateIntraEdiviFahrzeuge07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_fahrzeuge')) {
            return;
        }

        $this->table('intra_edivi_fahrzeuge', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',   'integer',   ['null' => false])
            ->addColumn('identifier', 'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('veh_type',   'string',    ['limit' => 255, 'null' => false])
            ->addColumn('doctor',     'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('active',     'boolean',   ['default' => 1, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();
    }
}
