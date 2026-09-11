<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Standard-Quicklinks fürs eNOTF-Dashboard: Gefahrgut-Datenbank der BAM,
 * OpenStreetMap, Fahrzeuginfo und der Link zurück in die Administration.
 */
class InsertIntraEnotfQuicklinks29122025 extends AbstractMigration
{
    private const ROWS = [
        ['title' => 'Datenb. Gefahrgut', 'url' => 'https://www.dgg.bam.de/quickinfo/de/', 'icon' => 'fa-solid fa-radiation', 'category' => 'schnellzugriff', 'sort_order' => 1, 'col_width' => 'col',   'active' => 1],
        ['title' => 'Openstreetmap',     'url' => 'https://www.openstreetmap.org/',      'icon' => 'fa-solid fa-map',       'category' => 'schnellzugriff', 'sort_order' => 2, 'col_width' => 'col',   'active' => 1],
        ['title' => 'Fahrzeuginfo',      'url' => 'fahrzeuginfo.php',                    'icon' => 'fa-solid fa-ambulance', 'category' => 'schnellzugriff', 'sort_order' => 3, 'col_width' => 'col-6', 'active' => 1],
        ['title' => 'Administration',    'url' => '../index.php',                        'icon' => 'fa-solid fa-toolbox',   'category' => 'verwaltung',     'sort_order' => 1, 'col_width' => 'col-6', 'active' => 1],
    ];

    public function up(): void
    {
        $this->table('intra_enotf_quicklinks')
            ->insert(self::ROWS)
            ->saveData();
    }

    public function down(): void
    {
        foreach (self::ROWS as $row) {
            $this->execute(sprintf(
                "DELETE FROM intra_enotf_quicklinks WHERE title = '%s' AND url = '%s'",
                $row['title'],
                $row['url']
            ));
        }
    }
}
