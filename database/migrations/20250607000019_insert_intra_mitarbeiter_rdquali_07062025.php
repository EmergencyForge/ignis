<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für die RD-Qualifikationen: "Keine", RettSan/NotSan (jeweils auch
 * i. A.), Notarzt/ärztin und Ärztliche/-r Leiter/-in RD. Die ids starten
 * bei 2 — so aus dem Ursprungssystem übernommen.
 */
class InsertIntraMitarbeiterRdquali07062025 extends AbstractMigration
{
    public function up(): void
    {
        $rows = [
            ['id' => 2, 'priority' => 1, 'name' => 'Rettungssanitäter/-in i. A.', 'name_m' => 'Rettungssanitäter i. A.', 'name_w' => 'Rettungssanitäterin i. A.', 'abkuerzung' => 'RettSan i.A.', 'none' => 0, 'trainable' => 0, 'created_at' => '2025-03-20 01:07:47'],
            ['id' => 3, 'priority' => 0, 'name' => 'Keine',                       'name_m' => 'Keine',                   'name_w' => 'Keine',                     'abkuerzung' => null,           'none' => 1, 'trainable' => 0, 'created_at' => '2025-03-20 01:08:48'],
            ['id' => 4, 'priority' => 2, 'name' => 'Rettungssanitäter/-in',       'name_m' => 'Rettungssanitäter',       'name_w' => 'Rettungssanitäterin',       'abkuerzung' => 'RettSan',      'none' => 0, 'trainable' => 1, 'created_at' => '2025-03-20 01:09:04'],
            ['id' => 5, 'priority' => 3, 'name' => 'Notfallsanitäter/-in i. A.',  'name_m' => 'Notfallsanitäter i. A.',  'name_w' => 'Notfallsanitäterin i. A.',  'abkuerzung' => 'NotSan i.A.',  'none' => 0, 'trainable' => 0, 'created_at' => '2025-03-20 01:09:31'],
            ['id' => 6, 'priority' => 4, 'name' => 'Notfallsanitäter/-in',        'name_m' => 'Notfallsanitäter',        'name_w' => 'Notfallsanitäterin',        'abkuerzung' => 'NotSan',       'none' => 0, 'trainable' => 1, 'created_at' => '2025-03-20 01:09:46'],
            ['id' => 7, 'priority' => 5, 'name' => 'Notarzt/ärztin',              'name_m' => 'Notarzt',                 'name_w' => 'Notärztin',                 'abkuerzung' => 'Notarzt',      'none' => 0, 'trainable' => 0, 'created_at' => '2025-03-20 01:10:00'],
            ['id' => 8, 'priority' => 6, 'name' => 'Ärztliche/-r Leiter/-in RD',  'name_m' => 'Ärztlicher Leiter RD',    'name_w' => 'Ärztliche Leiterin RD',     'abkuerzung' => null,           'none' => 0, 'trainable' => 0, 'created_at' => '2025-03-20 01:10:25'],
        ];

        $this->table('intra_mitarbeiter_rdquali')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_mitarbeiter_rdquali` WHERE `id` IN (2, 3, 4, 5, 6, 7, 8)');
    }
}
