<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erweitert den Kommentar der Spalte intra_notifications.type um den neuen
 * Benachrichtigungstyp "system". Typ und Länge der Spalte bleiben unverändert
 * (varchar(50) NOT NULL).
 */
class AlterIntraNotifications04112025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_notifications')
            ->changeColumn('type', 'string', [
                'limit'   => 50,
                'null'    => false,
                'comment' => 'notification type: antrag, protokoll, dokument, system',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('intra_notifications')
            ->changeColumn('type', 'string', [
                'limit'   => 50,
                'null'    => false,
                'comment' => 'notification type: antrag, protokoll, dokument',
            ])
            ->update();
    }
}
