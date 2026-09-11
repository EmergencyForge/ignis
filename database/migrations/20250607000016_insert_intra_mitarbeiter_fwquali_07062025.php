<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für die FW-Qualifikationen: "Keine" plus B1 (Grundausbildung) bis
 * B6 (A-Dienst). Die ids starten bei 2 — so aus dem Ursprungssystem
 * übernommen.
 */
class InsertIntraMitarbeiterFwquali07062025 extends AbstractMigration
{
    public function up(): void
    {
        $rows = [
            ['id' => 2, 'priority' => 0, 'shortname' => '-',  'name' => 'Keine',            'name_m' => 'Keine',         'name_w' => 'Keine',           'none' => 1, 'created_at' => '2025-03-20 01:11:16'],
            ['id' => 3, 'priority' => 1, 'shortname' => 'B1', 'name' => 'Grundausbildung',  'name_m' => 'Grundausbildung', 'name_w' => 'Grundausbildung', 'none' => 0, 'created_at' => '2025-03-20 01:11:32'],
            ['id' => 4, 'priority' => 2, 'shortname' => 'B2', 'name' => 'Maschinist/-in',   'name_m' => 'Maschinist',    'name_w' => 'Maschinistin',    'none' => 0, 'created_at' => '2025-03-20 01:11:46'],
            ['id' => 5, 'priority' => 3, 'shortname' => 'B3', 'name' => 'Gruppenführer/-in', 'name_m' => 'Gruppenführer', 'name_w' => 'Gruppenführerin', 'none' => 0, 'created_at' => '2025-03-20 01:12:06'],
            ['id' => 6, 'priority' => 4, 'shortname' => 'B4', 'name' => 'Zugführer/-in',    'name_m' => 'Zugführer',     'name_w' => 'Zugführerin',     'none' => 0, 'created_at' => '2025-03-20 01:12:23'],
            ['id' => 7, 'priority' => 5, 'shortname' => 'B5', 'name' => 'B-Dienst',         'name_m' => 'B-Dienst',      'name_w' => 'B-Dienst',        'none' => 0, 'created_at' => '2025-03-20 01:12:31'],
            ['id' => 8, 'priority' => 6, 'shortname' => 'B6', 'name' => 'A-Dienst',         'name_m' => 'A-Dienst',      'name_w' => 'A-Dienst',        'none' => 0, 'created_at' => '2025-03-20 01:12:41'],
        ];

        $this->table('intra_mitarbeiter_fwquali')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_mitarbeiter_fwquali` WHERE `id` IN (2, 3, 4, 5, 6, 7, 8)');
    }
}
