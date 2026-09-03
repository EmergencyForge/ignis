<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Transport- und Einsatzziele für das eDIVI-Protokoll (z. B. Kliniken oder
 * Abschlussarten ohne Transport). `transport` markiert echte Transportziele,
 * Sortierung über `priority`, Deaktivierung über `active`.
 */
class CreateIntraEdiviZiele07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_ziele')) {
            return;
        }

        $this->table('intra_edivi_ziele', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',   'integer',   ['null' => false])
            ->addColumn('identifier', 'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('transport',  'boolean',   ['null' => false, 'default' => 0])
            ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
            ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
