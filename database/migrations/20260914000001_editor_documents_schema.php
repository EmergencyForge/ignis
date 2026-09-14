<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Die Tabellen für den Dokumenten-Editor aus emergencyforge/editor.
 *
 * Beide Inhaltsspalten tragen ProseMirror-JSON: in der Vorlage die
 * `docSection`-Struktur mit gesperrten und freien Abschnitten, im Dokument
 * dieselbe Struktur mit ausgefüllten Feldern. Gerendert wird ausschließlich
 * über `EmergencyForge\Editor\Renderer`.
 *
 * Neben den bestehenden `intra_dokument_*`-Tabellen, nicht an ihrer Stelle:
 * das alte Canvas-System bleibt vorerst stehen und wird in einem zweiten
 * Schritt abgelöst, wenn die zehn Systemvorlagen im neuen Editor nachgebaut
 * sind. Deshalb auch die englischen Namen — sie kollidieren nicht.
 *
 * Ein Dokument hängt an genau einem Mitarbeiter; ignis kennt keine Akten.
 */
final class EditorDocumentsSchema extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_document_templates')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('category', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('content', 'json')
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['is_active'])
            ->create();

        $this->table('intra_documents')
            // Öffentliche Kennung, siehe App\Documents\Editor\DocumentId.
            // Sieben Zeichen, unabhängig vom Primärschlüssel, damit eine
            // geteilte URL nichts über den Bestand verrät.
            ->addColumn('docid', 'char', ['limit' => 7])
            // Phinx legt Primaerschluessel vorzeichenlos an; der
            // Fremdschluessel muss dieselbe Signatur tragen.
            ->addColumn('template_id', 'integer', ['null' => true, 'signed' => false])
            // intra_mitarbeiter.id ist dagegen aus alter Zeit und
            // vorzeichenbehaftet.
            ->addColumn('mitarbeiter_id', 'integer')
            ->addColumn('title', 'string', ['limit' => 200])
            ->addColumn('content', 'json')
            // entwurf | ausgestellt, einmaliger Übergang
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'entwurf'])
            // Beim Ausstellen eingefrorene Variablenwerte: ein Dokument von
            // gestern zeigt den Rang von gestern, nicht den von heute.
            ->addColumn('frozen_values', 'json', ['null' => true])
            ->addColumn('pdf_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('issued_by', 'integer', ['null' => true])
            ->addColumn('issued_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['docid'], ['unique' => true])
            ->addIndex(['mitarbeiter_id'])
            ->addIndex(['status'])
            ->addForeignKey('template_id', 'intra_document_templates', 'id', ['delete' => 'SET NULL'])
            ->addForeignKey('mitarbeiter_id', 'intra_mitarbeiter', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
