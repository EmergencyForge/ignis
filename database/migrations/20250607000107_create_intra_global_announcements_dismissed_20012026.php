<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Merkt sich pro User, welche globalen Announcements er ausgeblendet hat.
 * Ein Announcement kann je User nur einmal dismissed werden; beim Löschen
 * des Users verschwinden auch seine Dismissals (FK ON DELETE CASCADE).
 */
class CreateIntraGlobalAnnouncementsDismissed20012026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_global_announcements_dismissed')) {
            return;
        }

        $this->table('intra_global_announcements_dismissed', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('announcement_id', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('dismissed_at', 'datetime', ['null' => false])
            ->addIndex(['announcement_id', 'user_id'], ['unique' => true, 'name' => 'unique_user_announcement'])
            ->addIndex(['user_id'], ['name' => 'idx_user'])
            ->addIndex(['dismissed_at'], ['name' => 'idx_dismissed'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
