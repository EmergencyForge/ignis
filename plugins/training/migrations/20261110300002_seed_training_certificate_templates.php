<?php

declare(strict_types=1);

use App\Documents\Editor\SystemTemplates;
use Phinx\Migration\AbstractMigration;

/**
 * Die beiden Zertifikate für den Lehrgangsabschluss auf Installationen,
 * die ihre Systemvorlagen schon haben. Eine frische Installation bekommt
 * sie über `20260914000002_seed_editor_templates`, dann ist hier nichts zu
 * tun. Vorhandene Vorlagen mit demselben Namen bleiben, wie sie sind.
 */
final class SeedTrainingCertificateTemplates extends AbstractMigration
{
    private const NAMES = [SystemTemplates::TRAINING_DIPLOMA, SystemTemplates::TRAINING_COURSE];

    public function up(): void
    {
        if (!$this->hasTable('intra_document_templates')) {
            return;
        }
        $known = array_column($this->fetchAll('SELECT name FROM intra_document_templates') ?: [], 'name');
        $all   = SystemTemplates::all();
        $rows  = [];
        foreach (self::NAMES as $name) {
            if (in_array($name, $known, true)) {
                continue;
            }
            $rows[] = [
                'name'      => $name,
                'category'  => $all[$name]['category'],
                'content'   => json_encode($all[$name]['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'is_active' => 1,
            ];
        }
        if ($rows !== []) {
            $this->table('intra_document_templates')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        $this->getAdapter()->getConnection()->prepare(
            'DELETE FROM intra_document_templates WHERE name IN (?, ?)'
            . ' AND id NOT IN (SELECT template_id FROM intra_documents WHERE template_id IS NOT NULL)'
        )->execute(self::NAMES);
    }
}
