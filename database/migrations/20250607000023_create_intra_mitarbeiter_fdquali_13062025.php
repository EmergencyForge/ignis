<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fachdienste (Sachgebiete): Nummer und Name des Sachgebiets, disabled-Flag
 * zum Ausblenden ohne Löschen.
 */
class CreateIntraMitarbeiterFdquali13062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_fdquali')) {
            return;
        }

        $this->table('intra_mitarbeiter_fdquali', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('sgnr',       'integer', ['null' => false])
            ->addColumn('sgname',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('disabled',   'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();
    }
}
