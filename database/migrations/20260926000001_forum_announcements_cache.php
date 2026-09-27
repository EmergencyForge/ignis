<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dashboard-Widget "Ankündigungen" liest ab jetzt die Forum-Kategorie
 * Ankündigungen (Discourse) statt der Hub-Changelog-API. Der Cache
 * intra_changelog_cache bleibt, bekommt aber die Spalte `pinned`, damit
 * angepinnte Themen oben stehen und markiert werden.
 *
 * Alte Hub-Einträge und ihr ETag werden geleert: sie gehören zu einer
 * anderen Quelle, und der nächste changelog:refresh soll ohne
 * If-None-Match anfragen. down() stellt Spalte und Cron-Text wieder her,
 * den Cache-Inhalt nicht — der füllt sich beim nächsten Abruf neu.
 */
final class ForumAnnouncementsCache extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('intra_changelog_cache')) {
            $table = $this->table('intra_changelog_cache');
            if (!$table->hasColumn('pinned')) {
                $table->addColumn('pinned', 'boolean', ['default' => false, 'after' => 'tags'])->update();
            }
            $this->execute('DELETE FROM intra_changelog_cache');
        }
        if ($this->hasTable('intra_changelog_meta')) {
            $this->execute('DELETE FROM intra_changelog_meta');
        }
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute(
                "UPDATE intra_cron_jobs
                    SET name = 'Forum-Ankündigungen aktualisieren',
                        description = 'Holt alle 30 Minuten die Ankündigungen aus dem Forum.'
                  WHERE identifier = 'changelog.refresh'"
            );
        }
    }

    public function down(): void
    {
        if ($this->hasTable('intra_changelog_cache')) {
            $table = $this->table('intra_changelog_cache');
            if ($table->hasColumn('pinned')) {
                $table->removeColumn('pinned')->update();
            }
            $this->execute('DELETE FROM intra_changelog_cache');
        }
        if ($this->hasTable('intra_changelog_meta')) {
            $this->execute('DELETE FROM intra_changelog_meta');
        }
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute(
                "UPDATE intra_cron_jobs
                    SET name = 'Changelogs aktualisieren',
                        description = 'Holt alle 30 Minuten neue Changelog-Eintraege vom Hub.'
                  WHERE identifier = 'changelog.refresh'"
            );
        }
    }
}
