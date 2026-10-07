<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Formularwerte eingereichter Anträge als Key-Value-Zeilen (feldname/wert),
 * per FK an intra_antraege gebunden.
 */
class CreateIntraAntraegeDaten30092025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_antraege_daten')) {
            return;
        }

        $this->table('intra_antraege_daten', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('antrag_id', 'integer', ['null' => false])
            ->addColumn('feldname',  'string', ['limit' => 100, 'null' => false])
            ->addColumn('wert',      'text',   ['null' => true])
            ->addIndex(['antrag_id'], ['name' => 'idx_antrag'])
            ->addIndex(['feldname'],  ['name' => 'idx_feldname'])
            ->addForeignKey('antrag_id', 'intra_antraege', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
