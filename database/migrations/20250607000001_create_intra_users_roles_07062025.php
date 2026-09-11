<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Rollen-Tabelle: Name, Priorität, Farbe und JSON-Permissions pro Rolle,
 * plus Flags für Default-Rolle und Admin.
 */
class CreateIntraUsersRoles07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_users_roles')) {
            return;
        }

        $this->table('intra_users_roles', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('priority',    'integer',   ['default' => 0, 'null' => false])
            ->addColumn('name',        'string',    ['limit' => 255, 'null' => false])
            ->addColumn('color',       'string',    ['limit' => 255, 'null' => true])
            ->addColumn('permissions', 'text',      ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('default',     'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('admin',       'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at',  'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
