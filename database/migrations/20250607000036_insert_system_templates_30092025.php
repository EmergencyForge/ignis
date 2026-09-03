<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Legt die mitgelieferten System-Dokumentvorlagen an (Urkunden, Zertifikate,
 * Schreiben) — nur solche, die noch nicht existieren, damit bestehende
 * Installationen nichts überschrieben bekommen.
 */
class InsertSystemTemplates30092025 extends AbstractMigration
{
    private const TEMPLATES = [
        ['id' => 1,  'name' => 'Beförderungsurkunde',             'category' => 'urkunde',    'template_file' => 'befoerderung.html.twig'],
        ['id' => 2,  'name' => 'Entlassungsurkunde',              'category' => 'urkunde',    'template_file' => 'entlassung.html.twig'],
        ['id' => 5,  'name' => 'Ausbildungszertifikat',           'category' => 'zertifikat', 'template_file' => 'ausbildung.html.twig'],
        ['id' => 6,  'name' => 'Lehrgangszertifikat',             'category' => 'zertifikat', 'template_file' => 'lehrgang.html.twig'],
        ['id' => 7,  'name' => 'Lehrgangszertifikat Fachdienste', 'category' => 'zertifikat', 'template_file' => 'fachlehrgang.html.twig'],
        ['id' => 10, 'name' => 'Schriftliche Abmahnung',          'category' => 'schreiben',  'template_file' => 'abmahnung.html.twig'],
        ['id' => 11, 'name' => 'Vorläufige Dienstenthebung',      'category' => 'schreiben',  'template_file' => 'dienstenthebung.html.twig'],
        ['id' => 12, 'name' => 'Dienstentfernung',                'category' => 'schreiben',  'template_file' => 'dienstentfernung.html.twig'],
        ['id' => 13, 'name' => 'Außerordentliche Kündigung',      'category' => 'schreiben',  'template_file' => 'kuendigung.html.twig'],
        ['id' => 14, 'name' => 'Ernennungsurkunde',               'category' => 'urkunde',    'template_file' => 'ernennung.html.twig'],
    ];

    public function up(): void
    {
        // Entspricht dem früheren INSERT IGNORE: vorhandene IDs überspringen.
        $existing = [];
        foreach ($this->fetchAll('SELECT id FROM intra_dokument_templates') as $row) {
            $existing[(int) $row['id']] = true;
        }

        $rows = [];
        foreach (self::TEMPLATES as $tpl) {
            if (isset($existing[$tpl['id']])) {
                continue;
            }
            $rows[] = $tpl + ['is_system' => 1, 'config' => '{}'];
        }

        if ($rows !== []) {
            $this->table('intra_dokument_templates')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        $ids = implode(', ', array_column(self::TEMPLATES, 'id'));
        $this->execute("DELETE FROM intra_dokument_templates WHERE is_system = 1 AND id IN ($ids)");
    }
}
