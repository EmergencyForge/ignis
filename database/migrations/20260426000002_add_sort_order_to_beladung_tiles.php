<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sortier-Spalte für Beladelisten-Tiles, damit Crews die Reihenfolge per
 * Drag-and-Drop in der Admin-UI festlegen können (statt unverändert
 * alphabetisch zu sortieren). Default 0 — bestehende Tiles fallen mit
 * gleichem Wert in die alphabetische Sortierung als Sekundär-Kriterium.
 */
final class AddSortOrderToBeladungTiles extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $columns = $pdo->query("SHOW COLUMNS FROM intra_fahrzeuge_beladung_tiles LIKE 'sort_order'")->fetchAll();
        if (!$columns) {
            $pdo->exec(
                "ALTER TABLE intra_fahrzeuge_beladung_tiles
                 ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER amount,
                 ADD INDEX idx_category_sort (category, sort_order)"
            );
        }
    }
}
