<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Grundgerüst des Dokument-Template-Systems:
 *
 *   - `intra_dokument_templates`       — Vorlagen (Urkunden, Zertifikate, Schreiben)
 *   - `intra_dokument_template_fields` — konfigurierbare Felder pro Vorlage
 *
 * Zusätzlich bekommt `intra_mitarbeiter_dokumente` die Spalten `template_id`
 * und `custom_data`, damit bestehende Dokumente einer Vorlage zugeordnet und
 * ihre Feldwerte als JSON abgelegt werden können.
 */
class UpdateIntraMitarbeiterDokumente30092025Templates extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_dokument_templates')) {
            $this->table('intra_dokument_templates', [
                'id'        => 'id',
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('name',          'string',    ['limit' => 255, 'null' => false])
                ->addColumn('category',      'enum',      ['values' => ['urkunde', 'zertifikat', 'schreiben', 'sonstiges'], 'null' => true])
                ->addColumn('description',   'text',      ['null' => true])
                ->addColumn('template_file', 'string',    ['limit' => 255, 'null' => true])
                ->addColumn('config',        'json',      ['null' => true])
                ->addColumn('is_system',     'boolean',   ['default' => 0, 'null' => true])
                ->addColumn('created_by',    'integer',   ['null' => true])
                ->addColumn('created_at',    'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at',    'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        if (!$this->hasTable('intra_dokument_template_fields')) {
            $this->table('intra_dokument_template_fields', [
                'id'        => 'id',
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('template_id',      'integer', ['null' => false])
                ->addColumn('field_name',       'string',  ['limit' => 100, 'null' => false])
                ->addColumn('field_label',      'string',  ['limit' => 255, 'null' => false])
                ->addColumn('field_type',       'enum',    ['values' => ['text', 'textarea', 'date', 'select', 'number', 'richtext', 'dbdg', 'dbrd', 'db_dg', 'db_rdq'], 'null' => true])
                ->addColumn('field_options',    'text',    ['null' => true])
                ->addColumn('is_required',      'boolean', ['default' => 0, 'null' => true])
                ->addColumn('gender_specific',  'boolean', ['default' => 0, 'null' => true])
                ->addColumn('sort_order',       'integer', ['default' => 0, 'null' => true])
                ->addColumn('validation_rules', 'text',    ['null' => true])
                ->addForeignKey('template_id', 'intra_dokument_templates', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        $dokumente = $this->table('intra_mitarbeiter_dokumente');

        if (!$dokumente->hasColumn('template_id')) {
            $dokumente->addColumn('template_id', 'integer', ['null' => true, 'default' => null]);
        }

        if (!$dokumente->hasColumn('custom_data')) {
            $dokumente->addColumn('custom_data', 'text', ['null' => true, 'default' => null]);
        }

        $dokumente->update();
    }
}
