<?php

declare(strict_types=1);

use App\Documents\Editor\SystemTemplates;
use Phinx\Migration\AbstractMigration;

/**
 * Legt die zehn Systemvorlagen an, die ignis mitbringt. Was darin steht,
 * steht in {@see SystemTemplates} — hier geht es nur ums Einspielen.
 *
 * Läuft nur einmal: Vorlagen mit demselben Namen bleiben unangetastet,
 * damit eine bearbeitete Fassung eine erneute Migration überlebt.
 */
final class SeedEditorTemplates extends AbstractMigration
{
    public function up(): void
    {
        $existing = $this->fetchAll('SELECT name FROM intra_document_templates');
        $known    = array_column($existing ?: [], 'name');

        $rows = [];
        foreach (SystemTemplates::all() as $name => $tpl) {
            if (in_array($name, $known, true)) {
                continue;
            }
            $rows[] = [
                'name'      => $name,
                'category'  => $tpl['category'],
                'content'   => json_encode(
                    ['type' => 'doc', 'content' => $tpl['sections']],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
                'is_active' => 1,
            ];
        }

        if ($rows !== []) {
            $this->table('intra_document_templates')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        $names        = array_keys(SystemTemplates::all());
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        // Nur Vorlagen, an denen kein Dokument hängt — sonst verlöre ein
        // ausgestelltes Dokument seine Herkunft.
        $statement = $this->getAdapter()->getConnection()->prepare(
            'DELETE FROM intra_document_templates WHERE name IN (' . $placeholders . ')'
            . ' AND id NOT IN (SELECT template_id FROM intra_documents WHERE template_id IS NOT NULL)'
        );
        $statement->execute($names);
    }
}
