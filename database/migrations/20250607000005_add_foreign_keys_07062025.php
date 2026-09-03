<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Verkettet die Kern-Tabellen: intra_users.role zeigt auf intra_users_roles,
 * intra_audit_log.user auf intra_users. Kommt nachträglich, weil die Tabellen
 * in den vorherigen Migrationen ohne FKs angelegt wurden.
 */
class AddForeignKeys07062025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_users')
            ->addForeignKey('role', 'intra_users_roles', 'id', [
                'constraint' => 'FK_intra_users_intra_users_roles',
                'delete'     => 'NO_ACTION',
                'update'     => 'CASCADE',
            ])
            ->update();

        $this->table('intra_audit_log')
            ->addForeignKey('user', 'intra_users', 'id', [
                'constraint' => 'FK_intra_audit_log_intra_users',
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
            ])
            ->update();
    }
}
