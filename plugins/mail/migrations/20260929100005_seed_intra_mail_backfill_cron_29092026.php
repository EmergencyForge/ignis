<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Nächtlicher Lauf von `mail:backfill`: Mitarbeiter, die an den Events
 * vorbei angelegt oder geändert wurden (Import, direkte DB-Pflege),
 * bekommen ihr Postfach, freie Postfächer ihr Konto. Der Befehl ist
 * idempotent und hängt ein gebundenes Postfach nie um. Muster wie
 * 20260929100003_seed_intra_mail_cleanup_cron.
 */
final class SeedIntraMailBackfillCron29092026 extends AbstractMigration
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
            'identifier'   => 'mail.backfill',
            'name'         => 'Postfächer abgleichen',
            'description'  => 'Legt fehlende Postfächer an, legt die ausgeschiedener Mitarbeiter still und ordnet freie Postfächer ihrem eindeutigen Konto zu.',
            'handler_type' => 'console',
            'handler'      => 'mail:backfill',
            'schedule'     => '15 3 * * *',
            'config'       => json_encode(['timeout' => 300]),
            'active'       => 1,
            'is_builtin'   => 1,
        ]);
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'mail.backfill'");
        }
    }
}
