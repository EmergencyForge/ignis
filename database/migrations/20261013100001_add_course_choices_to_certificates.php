<?php

declare(strict_types=1);

use App\Documents\Editor\SystemTemplates;
use Phinx\Migration\AbstractMigration;

/**
 * Die beiden Lehrgangszertifikate bekommen ihre Auswahlliste zurück, die
 * mit dem alten Dokumentensystem verloren ging. Bisher war der Lehrgang
 * ein Freitextfeld.
 *
 * Geändert wird nur das Feld `qualifikation`, und nur wenn es noch keine
 * Liste hat. Wer die Vorlage umgebaut oder selbst eine Liste gepflegt hat,
 * behält seine Fassung.
 */
final class AddCourseChoicesToCertificates extends AbstractMigration
{
    private const TEMPLATES = [
        'Lehrgangszertifikat'             => SystemTemplates::LEHRGAENGE,
        'Lehrgangszertifikat Fachdienste' => SystemTemplates::FACHDIENSTE,
    ];

    public function up(): void
    {
        if (!$this->hasTable('intra_document_templates')) {
            return;
        }

        $connection = $this->getAdapter()->getConnection();
        $select     = $connection->prepare('SELECT id, content FROM intra_document_templates WHERE name = ?');
        $update     = $connection->prepare('UPDATE intra_document_templates SET content = ? WHERE id = ?');

        foreach (self::TEMPLATES as $name => $options) {
            $select->execute([$name]);
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $content = json_decode((string) $row['content'], true);
                if (!is_array($content)) {
                    continue;
                }

                $changed = false;
                $content = $this->withOptions($content, $options, $changed);
                if ($changed) {
                    $update->execute([
                        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        $row['id'],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Die Liste bleibt stehen. Ein Freitextfeld nimmt jeden ihrer Werte an.
    }

    /**
     * @param  array<mixed>                $node
     * @param  list<array<string,string>>  $options
     * @return array<mixed>
     */
    private function withOptions(array $node, array $options, bool &$changed): array
    {
        if (($node['type'] ?? null) === 'docField'
            && ($node['attrs']['fieldId'] ?? null) === 'qualifikation'
            && empty($node['attrs']['options'])
        ) {
            $node['attrs']['options'] = $options;
            $changed                  = true;

            return $node;
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = $this->withOptions($child, $options, $changed);
                }
            }
        }

        return $node;
    }
}
