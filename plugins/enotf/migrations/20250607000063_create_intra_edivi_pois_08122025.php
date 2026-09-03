<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * POI-Stamm für Einsatzorte und Ziele: Name plus Adresse (Straße,
 * Hausnummer, Ort, Ortsteil). Deaktivierung über `active`.
 */
class CreateIntraEdiviPois08122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_pois')) {
            return;
        }

        $this->table('intra_edivi_pois', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('strasse',    'string',    ['limit' => 255, 'null' => true])
            ->addColumn('hnr',        'string',    ['limit' => 50, 'null' => true])
            ->addColumn('ort',        'string',    ['limit' => 255, 'null' => false])
            ->addColumn('ortsteil',   'string',    ['limit' => 255, 'null' => true])
            ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
            ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
