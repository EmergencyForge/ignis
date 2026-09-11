<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt is_archived Spalte und Index zu intra_mitarbeiter_dokumente hinzu.
 * Ermöglicht Archivierung von Dokumenten (statt nur Löschen).
 */
class AlterIntraMitarbeiterDokumente02042026 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_mitarbeiter_dokumente');

        if (!$table->hasColumn('is_archived')) {
            $table
                ->addColumn('is_archived', 'boolean', [
                    'null'    => false,
                    'default' => 0,
                    'after'   => 'timestamp',
                ])
                ->addIndex(['is_archived'], ['name' => 'idx_dokumente_archived'])
                ->update();
        }
    }
}
