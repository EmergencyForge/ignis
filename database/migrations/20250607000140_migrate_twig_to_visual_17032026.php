<?php

declare(strict_types=1);

use App\Documents\TwigToVisualMigrator;
use Phinx\Migration\AbstractMigration;

/**
 * Migriert alle bestehenden Twig-Templates zu visuellen JSON-Layouts.
 * Voraussetzung: intra_dokument_template_layouts und die editor_type-Spalte
 * aus den vorherigen Migrationen müssen bereits existieren.
 */
class MigrateTwigToVisual17032026 extends AbstractMigration
{
    public function up(): void
    {
        if (
            !$this->hasTable('intra_dokument_template_layouts')
            || !$this->table('intra_dokument_templates')->hasColumn('editor_type')
        ) {
            return;
        }

        $pdo = $this->getAdapter()->getConnection();
        $migrator = new TwigToVisualMigrator($pdo);
        $migrator->migrateAll();
    }

    public function down(): void
    {
        // Nicht umkehrbar: die generierten Layouts sind nachträglich nicht von
        // manuell erstellten zu unterscheiden, und die ursprünglichen
        // Twig-Zuordnungen wären nur verlustbehaftet rekonstruierbar.
    }
}
