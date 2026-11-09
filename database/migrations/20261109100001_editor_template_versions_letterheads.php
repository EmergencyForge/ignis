<?php

declare(strict_types=1);

use EmergencyForge\Editor\Migration\SectionsToPackage;
use EmergencyForge\Editor\Package;
use Phinx\Migration\AbstractMigration;

/**
 * Vorlagen und Dokumente wechseln vom Abschnittsmodell zum Dokumentpaket
 * des Editors 0.6.
 *
 * Jede Vorlage bekommt ihre alte Form als Fassung 1 und die umgestellte als
 * Fassung 2. Entwürfe werden mit derselben Regel umgestellt (die alte
 * Vorlage entscheidet über Feld-ID-Kollisionen) und hängen an Fassung 2,
 * damit ihre IDs zur Vorlage passen. Ausgestellte Dokumente behalten ihren
 * Inhalt, ihr PDF ist ohnehin fest, und zeigen auf Fassung 1.
 *
 * Gelöscht wird eine Vorlage weiter nur, solange kein Dokument an ihr hängt
 * (sonst deaktiviert), deshalb reicht `is_active` und es braucht kein
 * weiches Löschen.
 */
final class EditorTemplateVersionsLetterheads extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_document_template_versions')
            ->addColumn('template_id', 'integer', ['signed' => false])
            ->addColumn('version', 'integer', ['signed' => false])
            ->addColumn('content', 'json')
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['template_id', 'version'], ['unique' => true])
            ->addForeignKey('template_id', 'intra_document_templates', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_letterheads')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('content', 'json')
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex(['deleted_at'])
            ->create();

        $this->table('intra_documents')
            ->addColumn('template_version_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'template_id'])
            ->addForeignKey('template_version_id', 'intra_document_template_versions', 'id', ['delete' => 'SET NULL'])
            ->update();

        $this->convert();
    }

    /**
     * Vorlagen bekommen ihre Fassung 1 zurück. Umgestellte Entwürfe bleiben
     * Pakete, ihren alten Inhalt hebt die Migration nicht auf; nach einem
     * Rückweg auf einer echten Instanz das Backup einspielen.
     */
    public function down(): void
    {
        $this->execute(
            'UPDATE intra_document_templates t JOIN intra_document_template_versions v ON v.template_id = t.id AND v.version = 1 SET t.content = v.content'
        );

        $this->table('intra_documents')
            ->dropForeignKey('template_version_id')
            ->removeColumn('template_version_id')
            ->update();
        $this->table('intra_letterheads')->drop()->save();
        $this->table('intra_document_template_versions')->drop()->save();
    }

    private function convert(): void
    {
        $versions = [];

        foreach ($this->fetchAll('SELECT id, content, created_by FROM intra_document_templates') as $template) {
            $id     = (int) $template['id'];
            $legacy = $this->decode($template['content']);

            if (Package::isPackage($legacy)) {
                $versions[$id] = ['legacy' => $legacy, 'v1' => $this->insertVersion($id, 1, $legacy, $template['created_by']), 'v2' => null];
                continue;
            }

            $package       = SectionsToPackage::convert($legacy);
            $versions[$id] = [
                'legacy' => $legacy,
                'v1'     => $this->insertVersion($id, 1, $legacy, $template['created_by']),
                'v2'     => $this->insertVersion($id, 2, $package, $template['created_by']),
            ];
            $this->execute(
                'UPDATE intra_document_templates SET content = ? WHERE id = ?',
                [$this->encode($package), $id],
            );
        }

        foreach ($this->fetchAll('SELECT id, template_id, status, content FROM intra_documents') as $document) {
            $known = $document['template_id'] !== null ? ($versions[(int) $document['template_id']] ?? null) : null;

            if ($document['status'] !== 'entwurf') {
                if ($known !== null) {
                    $this->execute('UPDATE intra_documents SET template_version_id = ? WHERE id = ?', [$known['v1'], $document['id']]);
                }
                continue;
            }

            // Ein Entwurf ohne Vorlage bleibt wie bisher schreibgeschützt,
            // wird aber umgestellt, damit der Editor ihn anzeigen kann.
            $package = SectionsToPackage::convert($this->decode($document['content']), $known['legacy'] ?? null);
            $this->execute(
                'UPDATE intra_documents SET content = ?, template_version_id = ? WHERE id = ?',
                [$this->encode($package), $known !== null ? ($known['v2'] ?? $known['v1']) : null, $document['id']],
            );
        }
    }

    /** @param array<string, mixed> $content */
    private function insertVersion(int $templateId, int $version, array $content, mixed $createdBy): int
    {
        $this->execute(
            'INSERT INTO intra_document_template_versions (template_id, version, content, created_by) VALUES (?, ?, ?, ?)',
            [$templateId, $version, $this->encode($content), $createdBy],
        );

        return (int) $this->getAdapter()->getConnection()->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : ['type' => 'doc', 'content' => []];
    }

    /** @param array<string, mixed> $content */
    private function encode(array $content): string
    {
        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
