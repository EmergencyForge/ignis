<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Nächtlicher Lauf von `kb:images:cleanup` (Plugin-Command aus console.php):
 * hochgeladene Artikelbilder, die kein Eintrag mehr verwendet. Muster wie
 * 20260929100003_seed_intra_mail_cleanup_cron im Mail-Plugin.
 */
final class SeedIntraKbImagesCleanupCron04102026 extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_cron_jobs')) {
            return;
        }

        $stmt = $this->getAdapter()->getConnection()->prepare(
            'INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config, active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, :handler_type, :handler, :schedule, :config, :active, :is_builtin, NOW())'
        );
        $stmt->execute([
            'identifier'   => 'kb.images-cleanup',
            'name'         => 'Artikelbilder aufräumen',
            'description'  => 'Entfernt hochgeladene Bilder der Wissensdatenbank, die seit einem Tag in keinem Eintrag mehr stehen.',
            'handler_type' => 'console',
            'handler'      => 'kb:images:cleanup',
            'schedule'     => '45 3 * * *',
            'config'       => json_encode(['timeout' => 120]),
            'active'       => 1,
            'is_builtin'   => 1,
        ]);
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'kb.images-cleanup'");
        }
    }
}
