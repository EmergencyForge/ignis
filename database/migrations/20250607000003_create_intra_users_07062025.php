<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Panel-Benutzer: Discord-basierter Login (discord_id), optionale Verknüpfung
 * zur Personalakte (aktenid) und Rollen-Zuordnung. Der FK auf
 * intra_users_roles kommt separat in add_foreign_keys_07062025.
 */
class CreateIntraUsers07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_users')) {
            return;
        }

        $this->table('intra_users', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('username',   'string',   ['limit' => 255, 'null' => false])
            ->addColumn('fullname',   'string',   ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('discord_id', 'string',   ['limit' => 255, 'null' => false])
            ->addColumn('aktenid',    'integer',  ['null' => true])
            ->addColumn('role',       'integer',  ['default' => 0, 'null' => false])
            ->addColumn('full_admin', 'boolean',  ['default' => 0, 'null' => false])
            ->addIndex(['role'], ['name' => 'FK_intra_users_intra_users_roles'])
            ->create();
    }
}
