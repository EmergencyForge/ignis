<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Strukturierte Log-Daten: intra_mitarbeiter_log bekommt eine
 * `metadata`-Spalte für zusätzliche Angaben pro Log-Eintrag.
 */
class AlterIntraMitarbeiterLog04112025 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_mitarbeiter_log');

        if ($table->hasColumn('metadata')) {
            return;
        }

        $table
            ->addColumn('metadata', 'text', [
                'null'    => true,
                'default' => null,
                'after'   => 'paneluser',
            ])
            ->update();
    }
}
