<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * PDF-Ablage für Dokumente: intra_mitarbeiter_dokumente bekommt Pfad und
 * Zeitpunkt der generierten PDF-Datei. Außerdem wird docid von int(11) auf
 * VARCHAR(15) umgestellt (alphanumerische Dokument-IDs) und für beide
 * Spalten je ein Index angelegt.
 */
class AlterIntraMitarbeiterDokumente03102025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_mitarbeiter_dokumente')
            ->addColumn('pdf_path', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'comment' => 'Pfad zur gespeicherten PDF-Datei',
                'after'   => 'custom_data',
            ])
            ->addColumn('pdf_generated_at', 'timestamp', [
                'null'    => true,
                'default' => null,
                'comment' => 'Zeitpunkt der PDF-Generierung',
                'after'   => 'pdf_path',
            ])
            ->addIndex(['pdf_path'], ['name' => 'idx_pdf_path'])
            ->addIndex(['docid'],    ['name' => 'idx_docid'])
            ->changeColumn('docid', 'string', ['limit' => 15, 'null' => false])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('intra_mitarbeiter_dokumente');

        // Ursprüngliche Definition aus create_intra_mitarbeiter_dokumente_07062025:
        // int(11) NOT NULL
        $table->changeColumn('docid', 'integer', ['null' => false]);

        if ($table->hasIndexByName('idx_pdf_path')) {
            $table->removeIndexByName('idx_pdf_path');
        }
        if ($table->hasIndexByName('idx_docid')) {
            $table->removeIndexByName('idx_docid');
        }

        $table
            ->removeColumn('pdf_generated_at')
            ->removeColumn('pdf_path')
            ->update();
    }
}
