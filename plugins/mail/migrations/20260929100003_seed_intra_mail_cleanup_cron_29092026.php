<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Nächtlicher Lauf von `mail:cleanup` (Plugin-Command aus console.php):
 * liegengebliebene Entwürfe und Anhänge von Nachrichten, die kein
 * Beteiligter mehr hat. Muster wie 20260505000002_seed_changelog_cron.
 */
final class SeedIntraMailCleanupCron29092026 extends AbstractMigration
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
            'identifier'   => 'mail.cleanup',
            'name'         => 'Mail aufräumen',
            'description'  => 'Entfernt alte, unangetastete Entwürfe und Anhänge von Mails, die niemand mehr hat.',
            'handler_type' => 'console',
            'handler'      => 'mail:cleanup',
            'schedule'     => '30 3 * * *',
            'config'       => json_encode(['timeout' => 120]),
            'active'       => 1,
            'is_builtin'   => 1,
        ]);
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'mail.cleanup'");
        }
    }
}
