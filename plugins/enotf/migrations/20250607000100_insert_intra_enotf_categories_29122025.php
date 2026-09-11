<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Standard-Kategorien für die eNOTF-Quicklinks: Schnellzugriff und
 * Verwaltung — die beiden Werte, die vorher als ENUM fest kodiert waren.
 */
class InsertIntraEnotfCategories29122025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_enotf_categories')
            ->insert([
                ['name' => 'Schnellzugriff', 'slug' => 'schnellzugriff', 'sort_order' => 1, 'active' => 1],
                ['name' => 'Verwaltung',     'slug' => 'verwaltung',     'sort_order' => 2, 'active' => 1],
            ])
            ->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_enotf_categories WHERE slug IN ('schnellzugriff', 'verwaltung')");
    }
}
