<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Feuerwehr-Qualifikationen (B1-B6): Kurzname und geschlechtsspezifische
 * Anzeigenamen, none-Flag markiert den "Keine"-Platzhalter.
 */
class CreateIntraMitarbeiterFwquali07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_fwquali')) {
            return;
        }

        $this->table('intra_mitarbeiter_fwquali', [
            'signed'     => true,
            'engine'     => 'InnoDB',
            'encoding'   => 'utf8mb4',
            'collation'  => 'utf8mb4_general_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('priority',   'integer', ['null' => false])
            ->addColumn('shortname',  'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_m',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_w',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('none',       'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();

        // Der Primärschlüssel trägt im Originalschema ein explizites USING BTREE.
        $this->execute(
            'ALTER TABLE `intra_mitarbeiter_fwquali` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`) USING BTREE'
        );
    }
}
