<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dienstgrade fürs Personal: Anzeigenamen inkl. geschlechtsspezifischer
 * Varianten (name_m/name_w), Badge-Bildpfad und Archiv-Flag.
 */
class CreateIntraMitarbeiterDienstgrade07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_dienstgrade')) {
            return;
        }

        $this->table('intra_mitarbeiter_dienstgrade', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',   'integer', ['null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_m',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_w',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('badge',      'string',    ['limit' => 255, 'null' => true])
            ->addColumn('archive',    'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();
    }
}
