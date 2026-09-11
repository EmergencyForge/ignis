<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für die BF-Dienstgradleiter: Angestellte/-r bis Leitende/-r
 * Branddirektor/-in, plus Ehrenamtliche und den Archiv-Eintrag
 * "Entlassen/Archiv".
 */
class InsertIntraMitarbeiterDienstgrade07062025 extends AbstractMigration
{
    public function up(): void
    {
        $rows = [
            ['id' => 1,  'priority' => 1,  'name' => 'Angestellte/-r',                'name_m' => 'Angestellter',              'name_w' => 'Angestellte',                 'badge' => null,                                     'archive' => 0, 'created_at' => '2025-03-20 00:51:26'],
            ['id' => 2,  'priority' => 2,  'name' => 'Brandmeisteranwärter/-in',      'name_m' => 'Brandmeisteranwärter',      'name_w' => 'Brandmeisteranwärterin',      'badge' => '/assets/img/dienstgrade/bf/1.png',       'archive' => 0, 'created_at' => '2025-03-20 00:52:59'],
            ['id' => 3,  'priority' => 3,  'name' => 'Brandmeister/-in',              'name_m' => 'Brandmeister',              'name_w' => 'Brandmeisterin',              'badge' => '/assets/img/dienstgrade/bf/2.png',       'archive' => 0, 'created_at' => '2025-03-20 00:53:27'],
            ['id' => 4,  'priority' => 4,  'name' => 'Oberbrandmeister/-in',          'name_m' => 'Oberbrandmeister',          'name_w' => 'Oberbrandmeisterin',          'badge' => '/assets/img/dienstgrade/bf/3.png',       'archive' => 0, 'created_at' => '2025-03-20 00:54:22'],
            ['id' => 5,  'priority' => 5,  'name' => 'Hauptbrandmeister/-in',         'name_m' => 'Hauptbrandmeister',         'name_w' => 'Hauptbrandmeisterin',         'badge' => '/assets/img/dienstgrade/bf/4.png',       'archive' => 0, 'created_at' => '2025-03-20 00:54:49'],
            ['id' => 6,  'priority' => 6,  'name' => 'Hauptbrandmeister/-in mit AZ',  'name_m' => 'Hauptbrandmeister mit AZ',  'name_w' => 'Hauptbrandmesiterin mit AZ',  'badge' => '/assets/img/dienstgrade/bf/5.png',       'archive' => 0, 'created_at' => '2025-03-20 00:55:17'],
            ['id' => 7,  'priority' => 8,  'name' => 'Brandinspektor/-in',            'name_m' => 'Brandinspektor',            'name_w' => 'Brandinspektorin',            'badge' => '/assets/img/dienstgrade/bf/6.png',       'archive' => 0, 'created_at' => '2025-03-20 00:55:46'],
            ['id' => 8,  'priority' => 9,  'name' => 'Oberbrandinspektor/-in',        'name_m' => 'Oberbrandinspektor',        'name_w' => 'Oberbrandinspektorin',        'badge' => '/assets/img/dienstgrade/bf/7.png',       'archive' => 0, 'created_at' => '2025-03-20 00:56:02'],
            ['id' => 9,  'priority' => 10, 'name' => 'Brandamtmann/frau',             'name_m' => 'Brandamtmann',              'name_w' => 'Brandamtfrau',                'badge' => '/assets/img/dienstgrade/bf/8.png',       'archive' => 0, 'created_at' => '2025-03-20 00:56:30'],
            ['id' => 10, 'priority' => 11, 'name' => 'Brandamtsrat/rätin',            'name_m' => 'Brandamtsrat',              'name_w' => 'Brandamtsrätin',              'badge' => '/assets/img/dienstgrade/bf/9.png',       'archive' => 0, 'created_at' => '2025-03-20 00:56:57'],
            ['id' => 11, 'priority' => 12, 'name' => 'Brandoberamtsrat/rätin',        'name_m' => 'Brandoberamtsrat',          'name_w' => 'Brandoberamtsrätin',          'badge' => '/assets/img/dienstgrade/bf/10.png',      'archive' => 0, 'created_at' => '2025-03-20 00:57:18'],
            ['id' => 12, 'priority' => 13, 'name' => 'Brandreferendar/-in',           'name_m' => 'Brandreferendar',           'name_w' => 'Brandreferendarin',           'badge' => '/assets/img/dienstgrade/bf/15.png',      'archive' => 0, 'created_at' => '2025-03-20 00:57:48'],
            ['id' => 13, 'priority' => 14, 'name' => 'Brandrat/rätin',                'name_m' => 'Brandrat',                  'name_w' => 'Brandrätin',                  'badge' => '/assets/img/dienstgrade/bf/11.png',      'archive' => 0, 'created_at' => '2025-03-20 00:58:33'],
            ['id' => 14, 'priority' => 15, 'name' => 'Oberbrandrat/rätin',            'name_m' => 'Oberbrandrat',              'name_w' => 'Oberbrandrätin',              'badge' => '/assets/img/dienstgrade/bf/12.png',      'archive' => 0, 'created_at' => '2025-03-20 00:58:35'],
            ['id' => 15, 'priority' => 7,  'name' => 'Brandinspektoranwärter/-in',    'name_m' => 'Brandinspektoranwärter',    'name_w' => 'Brandinspektoranwärterin',    'badge' => '/assets/img/dienstgrade/bf/17_2.png',    'archive' => 0, 'created_at' => '2025-03-20 00:59:35'],
            ['id' => 16, 'priority' => 0,  'name' => 'Ehrenamtliche/-r',              'name_m' => 'Ehrenamtlicher',            'name_w' => 'Ehrenamtliche',               'badge' => null,                                     'archive' => 0, 'created_at' => '2025-03-20 01:02:58'],
            ['id' => 17, 'priority' => 16, 'name' => 'Branddirektor/-in',             'name_m' => 'Branddirektor',             'name_w' => 'Branddirektorin',             'badge' => '/assets/img/dienstgrade/bf/13.png',      'archive' => 0, 'created_at' => '2025-03-20 01:03:56'],
            ['id' => 18, 'priority' => 17, 'name' => 'Leitende/-r Branddirektor/-in', 'name_m' => 'Leitender Branddirektor',   'name_w' => 'Leitende Branddirektorin',    'badge' => '/assets/img/dienstgrade/bf/14.png',      'archive' => 0, 'created_at' => '2025-03-20 01:04:28'],
            ['id' => 19, 'priority' => 0,  'name' => 'Entlassen/Archiv',              'name_m' => 'Entlassen/Archiv',          'name_w' => 'Entlassen/Archiv',            'badge' => null,                                     'archive' => 1, 'created_at' => '2025-03-20 02:10:36'],
        ];

        $this->table('intra_mitarbeiter_dienstgrade')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_mitarbeiter_dienstgrade` WHERE `id` BETWEEN 1 AND 19');
    }
}
