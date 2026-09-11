<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Antragstypen für das dynamische Antragssystem: Name, Icon, Sortierung und
 * optionaler Verweis auf eine Zieltabelle pro Typ.
 */
class CreateIntraAntragTypen30092025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_antrag_typen')) {
            return;
        }

        $this->table('intra_antrag_typen', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('name',         'string',    ['limit' => 255, 'null' => false])
            ->addColumn('beschreibung', 'text',      ['null' => true])
            ->addColumn('icon',         'string',    ['limit' => 50, 'null' => true, 'default' => 'fa-solid fa-file-alt'])
            ->addColumn('aktiv',        'boolean',   ['default' => 1, 'null' => false])
            ->addColumn('sortierung',   'integer',   ['null' => true, 'default' => 0])
            ->addColumn('tabelle_name', 'string',    ['limit' => 100, 'null' => true, 'comment' => 'Name der Zieltabelle (z.B. intra_antrag_bef)'])
            ->addColumn('erstellt_am',  'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('erstellt_von', 'integer',   ['null' => true])
            ->addIndex(['aktiv'], ['name' => 'idx_aktiv'])
            ->create();
    }
}
