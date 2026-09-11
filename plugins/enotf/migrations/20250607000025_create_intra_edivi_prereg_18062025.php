<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Voranmeldungen für die Klinik: eintreffende Patienten mit Priorität,
 * Ankunftszeit, anmeldendem Fahrzeug, Verdachtsdiagnose, Kurzstatus
 * (Kreislauf, GCS, intubiert) und Zielklinik.
 */
class CreateIntraEdiviPrereg18062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_prereg')) {
            return;
        }

        $this->table('intra_edivi_prereg', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',   'boolean',   ['null' => false, 'default' => 0])
            ->addColumn('arrival',    'datetime',  ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('fahrzeug',   'string',    ['limit' => 255, 'null' => true])
            ->addColumn('diagnose',   'string',    ['limit' => 255, 'null' => true])
            ->addColumn('geschlecht', 'boolean',   ['null' => false, 'default' => 0])
            ->addColumn('alter',      'string',    ['limit' => 255, 'null' => true])
            ->addColumn('text',       'text',      ['null' => true])
            ->addColumn('kreislauf',  'boolean',   ['null' => true, 'default' => 1])
            ->addColumn('gcs',        'string',    ['limit' => 255, 'null' => true, 'default' => '15'])
            ->addColumn('intubiert',  'boolean',   ['null' => true, 'default' => 0])
            ->addColumn('ziel',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('timestamp',  'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
            ->create();
    }
}
