<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hat die Twig-Vorlagen in visuelle Canvas-Layouts überführt.
 *
 * Ohne Wirkung, seit das Canvas-System abgelöst ist: `TwigToVisualMigrator`
 * ist gelöscht, und die Tabellen, in die diese Migration geschrieben hat,
 * wirft `20260914000003_drop_canvas_document_tables` ohnehin weg.
 *
 * Die Datei bleibt stehen, weil Phinx sie in `phinxlog` führt — wer sie
 * löscht, bekommt bei jeder bestehenden Installation eine Lücke in der
 * Migrationskette.
 */
class MigrateTwigToVisual17032026 extends AbstractMigration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
}
