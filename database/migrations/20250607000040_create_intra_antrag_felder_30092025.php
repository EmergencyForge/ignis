<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Felddefinitionen pro Antragstyp: Typ (Text, Datum, Select, ...), Pflicht,
 * Layout-Breite, Platzhalter und optionales Auto-Fill aus Mitarbeiterdaten.
 */
class CreateIntraAntragFelder30092025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_antrag_felder')) {
            return;
        }

        $this->table('intra_antrag_felder', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('antragstyp_id', 'integer', ['null' => false])
            ->addColumn('feldname',      'string',  ['limit' => 100, 'comment' => 'Technischer Name des Feldes', 'null' => false])
            ->addColumn('label',         'string',  ['limit' => 255, 'comment' => 'Anzeigetext für das Feld', 'null' => false])
            ->addColumn('feldtyp',       'enum',    ['values' => ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'email', 'time', 'tel'], 'null' => false])
            ->addColumn('optionen',      'text',    ['null' => true, 'comment' => 'JSON für Select-Optionen'])
            ->addColumn('pflichtfeld',   'boolean', ['default' => 0, 'null' => false])
            ->addColumn('platzhalter',   'string',  ['limit' => 255, 'null' => true])
            ->addColumn('sortierung',    'integer', ['null' => true, 'default' => 0])
            ->addColumn('breite',        'enum',    ['values' => ['full', 'half'], 'null' => true, 'default' => 'full'])
            ->addColumn('standardwert',  'string',  ['limit' => 255, 'null' => true])
            ->addColumn('hinweistext',   'text',    ['null' => true])
            ->addColumn('readonly',      'boolean', ['null' => true, 'default' => 0, 'comment' => 'Feld ist nur lesbar'])
            ->addColumn('auto_fill',     'string',  ['limit' => 50, 'null' => true, 'comment' => 'Automatisch ausfüllen mit: fullname, dienstnr, dienstgrad, discordtag'])
            ->addIndex(['antragstyp_id'], ['name' => 'idx_antragstyp'])
            ->addIndex(['sortierung'],    ['name' => 'idx_sortierung'])
            ->addForeignKey('antragstyp_id', 'intra_antrag_typen', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
