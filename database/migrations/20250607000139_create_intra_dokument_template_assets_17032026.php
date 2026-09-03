<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Datei-Assets für den visuellen Template-Editor (Bilder, Hintergründe,
 * Logos, Unterschriften). template_id NULL = geteiltes/globales Asset.
 */
class CreateIntraDokumentTemplateAssets17032026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_dokument_template_assets')) {
            return;
        }

        $this->table('intra_dokument_template_assets', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('template_id', 'integer', [
                'null'    => true,
                'default' => null,
                'comment' => 'NULL = shared/global asset',
            ])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('original_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('mime_type', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('file_size', 'integer', ['null' => false])
            ->addColumn('width_px', 'integer', ['null' => true, 'default' => null])
            ->addColumn('height_px', 'integer', ['null' => true, 'default' => null])
            ->addColumn('asset_type', 'enum', [
                'values'  => ['image', 'background', 'logo', 'signature'],
                'null'    => true,
                'default' => 'image',
            ])
            ->addColumn('uploaded_by', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['template_id', 'asset_type'], ['name' => 'idx_template_assets'])
            ->addForeignKey('template_id', 'intra_dokument_templates', 'id', [
                'delete'     => 'SET_NULL',
                'update'     => 'CASCADE',
                'constraint' => 'FK_template_assets_templates',
            ])
            ->create();
    }
}
