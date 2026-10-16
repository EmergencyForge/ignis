<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Das Forum ist abgeschaltet, die Ankündigungen kommen von emergencyforge.de
 * (siehe App\Hub\ChangelogClient). Nur der Text des Cron-Jobs nennt noch das
 * Forum. Cache und ETag bleiben: die Einträge verlinken auf
 * forum.emergencyforge.de, das auf die Website weiterleitet, und der nächste
 * Abruf ersetzt sie.
 */
final class AnnouncementsFromWebsite extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute(
                "UPDATE intra_cron_jobs
                    SET name = 'Ankündigungen aktualisieren',
                        description = 'Holt alle 30 Minuten die Ankündigungen von emergencyforge.de.'
                  WHERE identifier = 'changelog.refresh'"
            );
        }
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute(
                "UPDATE intra_cron_jobs
                    SET name = 'Forum-Ankündigungen aktualisieren',
                        description = 'Holt alle 30 Minuten die Ankündigungen aus dem Forum.'
                  WHERE identifier = 'changelog.refresh'"
            );
        }
    }
}
