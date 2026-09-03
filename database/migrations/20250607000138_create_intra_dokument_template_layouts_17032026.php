<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Canvas-Layouts für den visuellen Template-Editor: pro Template versionierte
 * Fabric.js-JSON-Exporte inklusive Seitenmaßen in Millimetern.
 */
class CreateIntraDokumentTemplateLayouts17032026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_dokument_template_layouts')) {
            return;
        }

        $this->table('intra_dokument_template_layouts', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('template_id', 'integer', ['null' => false])
            ->addColumn('version', 'integer', ['default' => 1, 'null' => false])
            ->addColumn('canvas_json', 'text', [
                'limit'   => MysqlAdapter::TEXT_LONG,
                'null'    => false,
                'comment' => 'Fabric.js JSON export of the full canvas',
            ])
            ->addColumn('page_width_mm', 'decimal', ['precision' => 6, 'scale' => 2, 'default' => 210.00, 'null' => false])
            ->addColumn('page_height_mm', 'decimal', ['precision' => 6, 'scale' => 2, 'default' => 297.00, 'null' => false])
            ->addColumn('background_image_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('is_active', 'boolean', ['null' => true, 'default' => 1])
            ->addColumn('created_by', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
                'null'    => false,
            ])
            ->addIndex(['template_id', 'is_active'], ['name' => 'idx_template_active'])
            ->addForeignKey('template_id', 'intra_dokument_templates', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'FK_template_layouts_templates',
            ])
            ->create();
    }
}
