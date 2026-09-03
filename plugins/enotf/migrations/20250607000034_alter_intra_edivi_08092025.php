<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Zugänge werden als flexible Liste erfasst: neue Spalte `c_zugang`
 * (LONGTEXT, JSON-Payload) hinter `c_ekg` statt der starren Dreier-Slots.
 */
class AlterIntraEdivi08092025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi')
            ->addColumn('c_zugang', 'text', ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true, 'after' => 'c_ekg'])
            ->update();
    }
}
