<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cache-Tabelle für globale Announcements vom intraRP-Hub. Die Einträge
 * werden periodisch vom Hub geholt (fetched_at) und lokal zwischengespeichert;
 * admin_only steuert, ob eine Meldung nur Admins angezeigt wird, valid_from/
 * valid_until begrenzen die Anzeige zeitlich.
 */
class CreateIntraGlobalAnnouncementsCache20012026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_global_announcements_cache')) {
            return;
        }

        $this->table('intra_global_announcements_cache', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('announcement_id', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('type', 'enum', [
                'values'  => ['info', 'warning', 'critical', 'success', 'update'],
                'default' => 'info',
                'null'    => true,
            ])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('link', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('priority', 'integer', ['default' => 0, 'null' => true])
            ->addColumn('admin_only', 'boolean', ['default' => 0, 'null' => true])
            ->addColumn('valid_from', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('valid_until', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('fetched_at', 'datetime', ['null' => false])
            ->addIndex(['announcement_id'], ['unique' => true, 'name' => 'unique_announcement'])
            ->addIndex(['type'], ['name' => 'idx_type'])
            ->addIndex(['priority'], ['name' => 'idx_priority'])
            ->addIndex(['admin_only'], ['name' => 'idx_admin_only'])
            ->addIndex(['valid_from', 'valid_until'], ['name' => 'idx_validity'])
            ->addIndex(['fetched_at'], ['name' => 'idx_fetched'])
            ->create();
    }
}
