<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Wissensdatenbank: Haupttabelle für Einträge.
 *
 * Drei Typen — general, medication, measure — mit typspezifischen Feldern
 * (Medikamente: Wirkstoff bis Besonderheiten; Maßnahmen: Wirkprinzip bis
 * Durchführung) plus Kompetenzlevel-Farbcodierung und CKEditor-HTML in
 * `content`.
 */
class CreateIntraKbEntries29112025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_kb_entries')) {
            return;
        }

        $this->table('intra_kb_entries', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('type',     'enum',   ['values' => ['general', 'medication', 'measure'], 'default' => 'general', 'null' => false])
            ->addColumn('title',    'string', ['limit' => 255, 'null' => false])
            ->addColumn('subtitle', 'string', ['limit' => 255, 'null' => true])

            // Farbcodierung nach Kompetenzlevel
            ->addColumn('competency_level', 'enum', ['values' => ['basis', 'rettsan', 'notsan_2c', 'notsan_2a', 'notarzt'], 'null' => true])

            // Freitext-Inhalt für alle Typen (HTML aus CKEditor)
            ->addColumn('content', 'text', ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true])

            // Medikament-spezifische Felder
            ->addColumn('med_wirkstoff',        'string', ['limit' => 255, 'null' => true])
            ->addColumn('med_wirkstoffgruppe',  'string', ['limit' => 255, 'null' => true])
            ->addColumn('med_wirkmechanismus',  'text',   ['null' => true])
            ->addColumn('med_indikationen',     'text',   ['null' => true])
            ->addColumn('med_kontraindikationen', 'text', ['null' => true])
            ->addColumn('med_uaw',              'text',   ['null' => true])
            ->addColumn('med_dosierung',        'text',   ['null' => true])
            ->addColumn('med_besonderheiten',   'text',   ['null' => true])

            // Maßnahmen-spezifische Felder
            ->addColumn('mass_wirkprinzip',       'text', ['null' => true])
            ->addColumn('mass_indikationen',      'text', ['null' => true])
            ->addColumn('mass_kontraindikationen', 'text', ['null' => true])
            ->addColumn('mass_risiken',           'text', ['null' => true])
            ->addColumn('mass_alternativen',      'text', ['null' => true])
            ->addColumn('mass_durchfuehrung',     'text', ['null' => true])

            // Metadaten
            ->addColumn('is_pinned',   'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('is_archived', 'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_by',  'integer',   ['null' => true])
            ->addColumn('updated_at',  'timestamp', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_by',  'integer',   ['null' => true])

            ->addIndex(['type'],             ['name' => 'idx_type'])
            ->addIndex(['is_pinned'],        ['name' => 'idx_pinned'])
            ->addIndex(['is_archived'],      ['name' => 'idx_archived'])
            ->addIndex(['competency_level'], ['name' => 'idx_competency'])
            ->addIndex(['created_at'],       ['name' => 'idx_created'])
            ->addIndex(['title'],            ['name' => 'idx_title'])

            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('updated_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
