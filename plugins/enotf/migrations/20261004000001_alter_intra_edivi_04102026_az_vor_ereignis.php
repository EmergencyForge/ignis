<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Fügt "AZ vor Ereignis" zur intra_edivi Tabelle hinzu (kein Pflichtfeld).
 *
 * - az_vor_ereignis: Allgemeinzustand vor dem Ereignis (1-5, 99=unbekannt)
 */
class AlterIntraEdivi04102026AzVorEreignis extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if ($table->hasColumn('az_vor_ereignis')) {
            return;
        }

        $table->addColumn('az_vor_ereignis', 'integer', [
            'limit' => MysqlAdapter::INT_TINY,
            'null'  => true,
            'after' => 'elokation',
        ])->update();
    }
}
