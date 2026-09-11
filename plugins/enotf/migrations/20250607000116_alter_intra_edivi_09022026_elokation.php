<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Fügt Einsatzort (Elokation) Feld zur intra_edivi Tabelle hinzu.
 *
 * - elokation: Einsatzort-Typ (1-11, 98=Sonstige, 99=nicht dokumentiert)
 */
class AlterIntraEdivi09022026Elokation extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if ($table->hasColumn('elokation')) {
            return;
        }

        $table->addColumn('elokation', 'integer', [
            'limit' => MysqlAdapter::INT_TINY,
            'null'  => true,
            'after' => 'naca_uebergabe',
        ])->update();
    }
}
