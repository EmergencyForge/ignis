<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Audit-Log: protokolliert Panel-Aktionen pro Benutzer mit Modul, Aktion und
 * Details. Der FK auf intra_users kommt separat in add_foreign_keys_07062025.
 */
class CreateIntraAuditLog07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_audit_log')) {
            return;
        }

        $this->table('intra_audit_log', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('user',      'integer',   ['default' => 0, 'null' => false])
            ->addColumn('module',    'string',    ['limit' => 255, 'null' => true])
            ->addColumn('action',    'text',      ['null' => true])
            ->addColumn('details',   'text',      ['null' => true])
            ->addColumn('global',    'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('timestamp', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['user'], ['name' => 'FK_intra_audit_log_intra_users'])
            ->create();
    }
}
