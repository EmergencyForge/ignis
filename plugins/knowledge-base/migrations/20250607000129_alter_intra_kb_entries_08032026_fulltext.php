<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erweitert die FULLTEXT-Indizes für die KB-Volltextsuche:
 *
 *   - `idx_kb_fulltext` deckte bisher nur title, subtitle, med_wirkstoff ab
 *     und wird auf title, subtitle, content umgestellt
 *   - neu: `idx_kb_fulltext_med` über die Medikament-Felder
 *   - neu: `idx_kb_fulltext_mass` über die Maßnahmen-Felder
 *
 * InnoDB kann pro ALTER TABLE nur einen FULLTEXT-Index anlegen, deshalb
 * separate update()-Aufrufe je Index.
 */
class AlterIntraKbEntries08032026Fulltext extends AbstractMigration
{
    public function up(): void
    {
        // Alten Hauptindex (title, subtitle, med_wirkstoff) entfernen ...
        if ($this->table('intra_kb_entries')->hasIndexByName('idx_kb_fulltext')) {
            $this->table('intra_kb_entries')
                ->removeIndexByName('idx_kb_fulltext')
                ->update();
        }

        // ... und erweitert (mit content statt med_wirkstoff) neu anlegen
        $this->table('intra_kb_entries')
            ->addIndex(['title', 'subtitle', 'content'], ['type' => 'fulltext', 'name' => 'idx_kb_fulltext'])
            ->update();

        // Medikament-Felder
        if (!$this->table('intra_kb_entries')->hasIndexByName('idx_kb_fulltext_med')) {
            $this->table('intra_kb_entries')
                ->addIndex(
                    ['med_wirkstoff', 'med_wirkstoffgruppe', 'med_indikationen', 'med_kontraindikationen', 'med_dosierung', 'med_besonderheiten'],
                    ['type' => 'fulltext', 'name' => 'idx_kb_fulltext_med']
                )
                ->update();
        }

        // Maßnahmen-Felder
        if (!$this->table('intra_kb_entries')->hasIndexByName('idx_kb_fulltext_mass')) {
            $this->table('intra_kb_entries')
                ->addIndex(
                    ['mass_indikationen', 'mass_kontraindikationen', 'mass_durchfuehrung', 'mass_risiken'],
                    ['type' => 'fulltext', 'name' => 'idx_kb_fulltext_mass']
                )
                ->update();
        }
    }

    public function down(): void
    {
        foreach (['idx_kb_fulltext_mass', 'idx_kb_fulltext_med', 'idx_kb_fulltext'] as $name) {
            if ($this->table('intra_kb_entries')->hasIndexByName($name)) {
                $this->table('intra_kb_entries')
                    ->removeIndexByName($name)
                    ->update();
            }
        }

        // Ursprünglichen Hauptindex (aus add_performance_indexes_16012026)
        // wiederherstellen
        $this->table('intra_kb_entries')
            ->addIndex(['title', 'subtitle', 'med_wirkstoff'], ['type' => 'fulltext', 'name' => 'idx_kb_fulltext'])
            ->update();
    }
}
