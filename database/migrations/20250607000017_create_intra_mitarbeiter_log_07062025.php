<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Änderungsprotokoll pro Personalakte: Log-Einträge mit Typ, Inhalt und dem
 * Panel-Benutzer, der die Änderung ausgelöst hat. PK heißt historisch logid.
 */
class CreateIntraMitarbeiterLog07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_log')) {
            return;
        }

        $this->table('intra_mitarbeiter_log', [
            'id'        => 'logid',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('profilid',  'integer', ['null' => false])
            ->addColumn('type',      'boolean',  ['default' => 0, 'null' => false])
            ->addColumn('content',   'text',     ['limit' => MysqlAdapter::TEXT_LONG, 'null' => false])
            ->addColumn('datetime',  'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('paneluser', 'string',   ['limit' => 255, 'null' => false])
            ->create();
    }
}
