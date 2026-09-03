<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Soft-Delete für Benutzer: Accounts können deaktiviert statt gelöscht werden.
 * is_active = 1 (aktiv), is_active = 0 (deaktiviert); deactivated_at/-_by
 * halten fest, wann und durch wen die Deaktivierung erfolgte.
 */
class AlterIntraUsers08032026SoftDelete extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_users');

        if (!$table->hasColumn('is_active')) {
            $table->addColumn('is_active', 'boolean', [
                'null'    => false,
                'default' => 1,
                'after'   => 'full_admin',
            ]);
        }
        if (!$table->hasColumn('deactivated_at')) {
            $table->addColumn('deactivated_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'is_active',
            ]);
        }
        if (!$table->hasColumn('deactivated_by')) {
            $table->addColumn('deactivated_by', 'integer', [
                'null'    => true,
                'default' => null,
                'after'   => 'deactivated_at',
            ]);
        }

        $table->update();
    }
}
