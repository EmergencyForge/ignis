<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * In-App-Benachrichtigungen pro Nutzer: Typ (antrag, protokoll, dokument),
 * Titel, Nachricht, Link zum betroffenen Element und Gelesen-Status.
 */
class CreateIntraNotifications03112025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_notifications')) {
            return;
        }

        $this->table('intra_notifications', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('user_id',    'integer',  ['comment' => 'ID of user to notify', 'null' => false])
            ->addColumn('type',       'string',   ['limit' => 50, 'comment' => 'notification type: antrag, protokoll, dokument'])
            ->addColumn('title',      'string',   ['limit' => 255, 'comment' => 'Notification title', 'null' => false])
            ->addColumn('message',    'text',     ['null' => true, 'comment' => 'Notification message'])
            ->addColumn('link',       'string',   ['limit' => 512, 'null' => true, 'comment' => 'Link to related item'])
            ->addColumn('is_read',    'boolean',  ['default' => 0, 'comment' => '0=unread, 1=read', 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('read_at',    'datetime', ['null' => true])
            ->addIndex(['user_id'],            ['name' => 'FK_intra_notifications_user'])
            ->addIndex(['user_id', 'is_read'], ['name' => 'idx_user_read'])
            ->addIndex(['created_at'],         ['name' => 'idx_created'])
            ->addForeignKey('user_id', 'intra_users', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'FK_intra_notifications_user',
            ])
            ->create();
    }
}
