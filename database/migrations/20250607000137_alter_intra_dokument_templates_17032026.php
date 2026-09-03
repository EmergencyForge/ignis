<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Vorbereitung für den visuellen Template-Editor: editor_type unterscheidet
 * Twig- und Visual-Templates, layout_id verweist auf das aktive Canvas-Layout.
 */
class AlterIntraDokumentTemplates17032026 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_dokument_templates');

        if (!$table->hasColumn('editor_type')) {
            $table->addColumn('editor_type', 'enum', [
                'values'  => ['twig', 'visual'],
                'default' => 'twig',
                'null'    => false,
                'after'   => 'template_file',
            ]);
        }
        if (!$table->hasColumn('layout_id')) {
            $table->addColumn('layout_id', 'integer', [
                'null'    => true,
                'default' => null,
                'after'   => 'editor_type',
            ]);
        }

        $table->update();
    }
}
