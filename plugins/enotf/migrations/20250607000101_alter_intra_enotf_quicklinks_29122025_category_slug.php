<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Stellt die Quicklinks von der fest kodierten ENUM-Kategorie auf den
 * Slug-Bezug zur Tabelle `intra_enotf_categories` um: neue Spalte
 * `category_slug` (aus `category` befüllt, danach NOT NULL + Index),
 * die alte ENUM-Spalte `category` entfällt.
 */
class AlterIntraEnotfQuicklinks29122025CategorySlug extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('intra_enotf_quicklinks');

        if (!$table->hasColumn('category_slug')) {
            $table->addColumn('category_slug', 'string', ['limit' => 100, 'null' => true, 'after' => 'category'])
                ->update();
        }

        // Bestehende Kategorie-Werte in die neue Slug-Spalte übernehmen.
        $this->execute('UPDATE intra_enotf_quicklinks SET category_slug = category');

        $table->changeColumn('category_slug', 'string', ['limit' => 100, 'null' => false])
            ->update();

        $table->addIndex(['category_slug'], ['name' => 'idx_category_slug'])
            ->removeColumn('category')
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('intra_enotf_quicklinks');

        $table->addColumn('category', 'enum', [
            'values'  => ['schnellzugriff', 'verwaltung'],
            'default' => 'schnellzugriff',
            'after'   => 'icon',
        ])->update();

        // Nur die beiden ursprünglichen ENUM-Werte lassen sich zurückschreiben;
        // später angelegte Slugs fallen auf den Default 'schnellzugriff' zurück.
        $this->execute("UPDATE intra_enotf_quicklinks SET category = category_slug WHERE category_slug IN ('schnellzugriff', 'verwaltung')");

        // idx_category_slug fällt mit der Spalte weg.
        $table->removeColumn('category_slug')->update();
    }
}
