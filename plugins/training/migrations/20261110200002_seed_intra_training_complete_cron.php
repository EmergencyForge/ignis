<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `training:complete` alle fünf Minuten: schließt Prüfungsversuche, deren
 * Frist samt Kulanz verstrichen ist.
 */
final class SeedIntraTrainingCompleteCron extends AbstractMigration
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
            'identifier'   => 'training.complete',
            'name'         => 'Prüfungsversuche abschließen',
            'description'  => 'Gibt Prüfungsversuche ab, deren Zeit abgelaufen ist, mit allem, was bis dahin gespeichert war.',
            'handler_type' => 'console',
            'handler'      => 'training:complete',
            'schedule'     => '*/5 * * * *',
            'config'       => json_encode(['timeout' => 120]),
            'active'       => 1,
            'is_builtin'   => 1,
        ]);
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'training.complete'");
        }
    }
}
