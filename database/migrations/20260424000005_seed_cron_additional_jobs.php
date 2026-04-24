<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Zusätzliche Built-in-Jobs für das Cron-System: Federation-Sync, Storage-
 * Cleanup und Update-Check. Werden nur eingefügt, wenn sie noch nicht
 * existieren (`INSERT IGNORE` auf Identifier-Unique).
 */
class SeedCronAdditionalJobs extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config,
                 active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, :handler_type, :handler, :schedule, :config,
                 :active, :is_builtin, UTC_TIMESTAMP())"
        );

        $jobs = [
            [
                'identifier'   => 'federation.sync',
                'name'         => 'Federation-Sync',
                'description'  => 'Syncht alle aktiven Federation-Links, deren Interval abgelaufen ist (Personal, eNOTF, Einsätze).',
                'handler_type' => 'console',
                'handler'      => 'federation:sync',
                'schedule'     => '*/10 * * * *',
                'config'       => json_encode(['timeout' => 90]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
            [
                'identifier'   => 'storage.cleanup',
                'name'         => 'Storage-Cleanup',
                'description'  => 'Entfernt alte Updater-Temp-Verzeichnisse (>24h), alte failed_jobs (>30 Tage) und alte Cron-Run-Historie.',
                'handler_type' => 'console',
                'handler'      => 'storage:cleanup',
                'schedule'     => '0 4 * * 0',
                'config'       => json_encode(['timeout' => 120]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
            [
                'identifier'   => 'updates.check',
                'name'         => 'Update-Check',
                'description'  => 'Prüft einmal täglich auf neue Releases und aktualisiert den Update-Cache.',
                'handler_type' => 'console',
                'handler'      => 'updates:check',
                'schedule'     => '23 5 * * *',
                'config'       => json_encode(['timeout' => 30]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
        ];

        foreach ($jobs as $job) {
            $stmt->execute([
                'identifier'   => $job['identifier'],
                'name'         => $job['name'],
                'description'  => $job['description'],
                'handler_type' => $job['handler_type'],
                'handler'      => $job['handler'],
                'schedule'     => $job['schedule'],
                'config'       => $job['config'],
                'active'       => $job['active'],
                'is_builtin'   => $job['is_builtin'],
            ]);
        }
    }
}
