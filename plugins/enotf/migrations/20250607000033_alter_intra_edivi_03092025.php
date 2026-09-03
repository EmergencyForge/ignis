<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Anlagezeitpunkt für eDIVI-Protokolle: `created_at` mit automatischem
 * Timestamp, eingeordnet hinter `last_edit`.
 */
class AlterIntraEdivi03092025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi')
            ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'after' => 'last_edit'])
            ->update();
    }
}
