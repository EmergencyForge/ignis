<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für die Fachdienste: Sachgebiete 211-233 (Leitstelle, Einsatzleitdienst,
 * Presse, FW Schule, Personal, Logistik, Spezialrettung, CBRN, KIT) und
 * 411-414 (RD Schule, EL RD, Luftrettung, QM RD).
 */
class InsertIntraMitarbeiterFdquali13062025 extends AbstractMigration
{
    public function up(): void
    {
        $rows = [
            ['id' => 1,  'sgnr' => 211, 'sgname' => 'Integrierte Leitstelle',   'disabled' => 0, 'created_at' => '2025-06-13 13:04:15'],
            ['id' => 2,  'sgnr' => 212, 'sgname' => 'Einsatzleitdienst',        'disabled' => 0, 'created_at' => '2025-06-13 13:04:38'],
            ['id' => 3,  'sgnr' => 213, 'sgname' => 'Presseabteilung',          'disabled' => 0, 'created_at' => '2025-06-13 13:05:01'],
            ['id' => 4,  'sgnr' => 221, 'sgname' => 'FW Schule',                'disabled' => 0, 'created_at' => '2025-06-13 13:05:08'],
            ['id' => 5,  'sgnr' => 222, 'sgname' => 'Personaleinsatz FW',       'disabled' => 0, 'created_at' => '2025-06-13 13:05:17'],
            ['id' => 6,  'sgnr' => 223, 'sgname' => 'Lager und Logistik',       'disabled' => 0, 'created_at' => '2025-06-13 13:05:25'],
            ['id' => 7,  'sgnr' => 231, 'sgname' => 'Spezialrettung',           'disabled' => 0, 'created_at' => '2025-06-13 13:05:32'],
            ['id' => 8,  'sgnr' => 232, 'sgname' => 'CBRN-SChutz',              'disabled' => 0, 'created_at' => '2025-06-13 13:05:46'],
            ['id' => 9,  'sgnr' => 233, 'sgname' => 'Krisenintervention',       'disabled' => 0, 'created_at' => '2025-06-13 13:05:57'],
            ['id' => 10, 'sgnr' => 411, 'sgname' => 'RD Schule',                'disabled' => 0, 'created_at' => '2025-06-13 13:06:19'],
            ['id' => 11, 'sgnr' => 412, 'sgname' => 'Einsatzleitung RD',        'disabled' => 0, 'created_at' => '2025-06-13 13:06:43'],
            ['id' => 12, 'sgnr' => 413, 'sgname' => 'Luftrettung',              'disabled' => 0, 'created_at' => '2025-06-13 13:06:50'],
            ['id' => 13, 'sgnr' => 414, 'sgname' => 'Qualitätsmanagement RD',   'disabled' => 0, 'created_at' => '2025-06-13 13:06:59'],
        ];

        $this->table('intra_mitarbeiter_fdquali')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_mitarbeiter_fdquali` WHERE `id` BETWEEN 1 AND 13');
    }
}
