<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Medikamentenstamm für das eDIVI-Protokoll: Wirkstoff (eindeutig),
 * optionaler Herstellername und wählbare Dosierungen als kommaseparierte
 * Liste. Sortierung über `priority`, Deaktivierung über `active`.
 */
class CreateIntraEdiviMedikamente02122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_medikamente')) {
            return;
        }

        $this->table('intra_edivi_medikamente', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',        'integer',   ['null' => false, 'default' => 0])
            ->addColumn('wirkstoff',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('herstellername',  'string',    ['limit' => 255, 'null' => true])
            ->addColumn('dosierungen',     'text',      ['null' => true])
            ->addColumn('active',          'boolean',   ['null' => false, 'default' => 1])
            ->addColumn('created_at',      'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['wirkstoff'], ['unique' => true, 'name' => 'unique_wirkstoff'])
            ->create();
    }
}
