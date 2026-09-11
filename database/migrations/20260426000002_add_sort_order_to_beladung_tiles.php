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
        $table = $this->table('intra_fahrzeuge_beladung_tiles');

        if (!$table->hasColumn('sort_order')) {
            $table
                ->addColumn('sort_order', 'integer', [
                    'null'    => false,
                    'default' => 0,
                    'after'   => 'amount',
                ])
                ->addIndex(['category', 'sort_order'], ['name' => 'idx_category_sort'])
                ->update();
        }
    }
}
