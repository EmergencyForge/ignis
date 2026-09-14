<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

/**
 * Räumt die Tabellen des abgelösten Canvas-Dokumentensystems ab.
 *
 * Was bleibt: `intra_mitarbeiter_dokumente`. Dort stehen die ausgestellten
 * Dokumente, und ihre PDFs liegen unter `storage/documents/`. Eine
 * ausgestellte Urkunde darf nicht verschwinden, nur weil das Werkzeug
 * gewechselt hat. Die Spalte `template_id` behält ihren Wert, verliert
 * aber ihren Fremdschlüssel — sie zeigt ab hier ins Leere und wird nur
 * noch als historische Notiz geführt.
 *
 * Was geht: Vorlagen, ihre Felder, Layouts und Assets sowie die
 * Kategorien. Neue Dokumente entstehen im Editor
 * (`intra_document_templates`), und dessen Vorlagen haben mit diesen
 * nichts gemeinsam — ein Fabric.js-Canvas lässt sich nicht in ein
 * fließendes ProseMirror-Dokument übersetzen.
 */
final class DropCanvasDocumentTables extends AbstractMigration
{
    private const DROP = [
        'intra_dokument_template_fields',
        'intra_dokument_template_layouts',
        'intra_dokument_template_assets',
        'intra_dokument_templates',
        'intra_dokument_kategorien',
    ];

    public function up(): void
    {
        // Erst der Fremdschlüssel auf die Vorlagen, sonst weigert sich
        // MySQL, die Elterntabelle zu werfen.
        $dokumente = $this->table('intra_mitarbeiter_dokumente');
        if ($dokumente->hasForeignKey('template_id')) {
            $dokumente->dropForeignKey('template_id')->update();
        }

        foreach (self::DROP as $table) {
            if ($this->hasTable($table)) {
                $this->table($table)->drop()->save();
            }
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Die Vorlagen des Canvas-Systems lassen sich nicht wiederherstellen — '
            . 'ihr Inhalt ist mit den Tabellen weg. Ein Backup von vor der Migration einspielen.'
        );
    }
}
