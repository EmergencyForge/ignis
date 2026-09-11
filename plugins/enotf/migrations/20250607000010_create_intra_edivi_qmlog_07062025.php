<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * QM-Log für eDIVI-Protokolle: Kommentare und Statusaktionen der
 * Qualitätssicherung je Protokoll. Einträge hängen per FK am Protokoll
 * und werden beim Löschen des Protokolls mitentfernt.
 */
class CreateIntraEdiviQmlog07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_qmlog')) {
            return;
        }

        $this->table('intra_edivi_qmlog', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('protokoll_id', 'integer',   ['null' => false])
            ->addColumn('kommentar',    'text',      ['limit' => MysqlAdapter::TEXT_LONG, 'null' => false])
            ->addColumn('log_aktion',   'boolean',   ['null' => true])
            ->addColumn('bearbeiter',   'string',    ['limit' => 255, 'null' => true])
            ->addColumn('timestamp',    'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['protokoll_id'], ['name' => 'FK_intra_edivi_qmlog_intra_edivi'])
            ->addForeignKey('protokoll_id', 'intra_edivi', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'FK_intra_edivi_qmlog_intra_edivi',
            ])
            ->create();
    }
}
