<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Registrierungscodes: Einmal-Codes für die Nutzer-Registrierung, mit
 * Ersteller, Einlöser und Einlöse-Zeitpunkt.
 */
class CreateIntraRegistrationCodes02112025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_registration_codes')) {
            return;
        }

        $this->table('intra_registration_codes', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('code',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('created_by', 'integer',   ['null' => true])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('used_by',    'integer',   ['null' => true])
            ->addColumn('used_at',    'timestamp', ['null' => true, 'default' => null])
            ->addColumn('is_used',    'boolean',   ['null' => true, 'default' => 0])
            ->addIndex(['code'],       ['unique' => true, 'name' => 'code'])
            ->addIndex(['created_by'], ['name' => 'created_by'])
            ->addIndex(['used_by'],    ['name' => 'used_by'])
            ->create();
    }
}
