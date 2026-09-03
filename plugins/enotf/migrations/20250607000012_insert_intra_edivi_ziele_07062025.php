<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für `intra_edivi_ziele`: die Standard-Abschlussarten ohne
 * Kliniktransport (ambulante Versorgung, Übergaben, Fehleinsatz,
 * nicht transportfähig).
 */
class InsertIntraEdiviZiele07062025 extends AbstractMigration
{
    private const SEED_IDS = [2, 3, 4, 5, 6];

    public function up(): void
    {
        $rows = [
            ['id' => 2, 'priority' => 110, 'identifier' => 'amb',   'name' => 'ambulante Versorgung vor Ort',          'transport' => 0, 'active' => 1, 'created_at' => '2025-03-19 22:32:15'],
            ['id' => 3, 'priority' => 125, 'identifier' => 'ubgnf', 'name' => 'Übergabe Notfallteam',                  'transport' => 0, 'active' => 1, 'created_at' => '2025-03-19 22:32:22'],
            ['id' => 4, 'priority' => 130, 'identifier' => 'kp',    'name' => 'Fehleinsatz - kein Patient',            'transport' => 0, 'active' => 1, 'created_at' => '2025-03-19 22:32:36'],
            ['id' => 5, 'priority' => 120, 'identifier' => 'ubg',   'name' => 'Übergabe an anderes Rettungsmittel',    'transport' => 0, 'active' => 1, 'created_at' => '2025-03-19 22:32:42'],
            ['id' => 6, 'priority' => 140, 'identifier' => 'ntrf',  'name' => 'Patient nicht transportfähig',          'transport' => 0, 'active' => 1, 'created_at' => '2025-03-19 22:32:42'],
        ];

        $this->table('intra_edivi_ziele')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_edivi_ziele` WHERE `id` IN (' . implode(',', self::SEED_IDS) . ')');
    }
}
