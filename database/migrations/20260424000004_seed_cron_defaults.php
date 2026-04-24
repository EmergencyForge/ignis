<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Setzt den Default-Token für `public/cron.php` (falls noch keiner vorhanden)
 * und registriert die eingebauten Cron-Jobs für Queue-Worker, Telemetrie
 * und Announcements-Refresh.
 *
 * `INSERT IGNORE` stellt sicher, dass bestehende Installationen keine
 * Werte überschreiben — der generierte Token bleibt erhalten, Custom-Jobs
 * mit gleichem Identifier werden nicht überschrieben.
 */
class SeedCronDefaults extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // CRON_ENDPOINT_TOKEN generieren (nur wenn noch nicht vorhanden)
        $token = bin2hex(random_bytes(32));
        $cfgStmt = $pdo->prepare(
            "INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
             VALUES (:key, :value, :type, :category, :description, :editable, :order)
             ON DUPLICATE KEY UPDATE
                config_type = VALUES(config_type),
                category = VALUES(category),
                description = VALUES(description),
                is_editable = VALUES(is_editable),
                display_order = VALUES(display_order)"
        );
        $cfgStmt->execute([
            'key'         => 'CRON_ENDPOINT_TOKEN',
            'value'       => $token,
            'type'        => 'string',
            'category'    => 'system',
            'description' => 'Token zum Aufruf von /cron.php (für externe Cron-Dienste wie cron-job.org)',
            'editable'    => 1,
            'order'       => 120,
        ]);

        // Built-in Cron-Jobs
        $jobStmt = $pdo->prepare(
            "INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config,
                 active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, :handler_type, :handler, :schedule, :config,
                 :active, :is_builtin, NOW())"
        );

        $builtIns = [
            [
                'identifier'   => 'queue.work',
                'name'         => 'Queue-Worker',
                'description'  => 'Arbeitet asynchrone Jobs aus der intra_jobs-Tabelle ab (max. 50 Jobs / 55s pro Lauf).',
                'handler_type' => 'console',
                'handler'      => 'queue:work',
                'schedule'     => '*/5 * * * *',
                'config'       => json_encode([
                    'args'    => ['--max-jobs=50', '--max-time=55'],
                    'timeout' => 60,
                ]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
            [
                'identifier'   => 'telemetry.heartbeat',
                'name'         => 'Telemetrie-Heartbeat',
                'description'  => 'Sendet täglich anonyme Statistiken an den EmergencyForge-Hub (nur wenn Telemetrie aktiviert).',
                'handler_type' => 'console',
                'handler'      => 'telemetry:send',
                'schedule'     => '17 3 * * *',
                'config'       => json_encode(['timeout' => 30]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
            [
                'identifier'   => 'announcements.refresh',
                'name'         => 'Announcements aktualisieren',
                'description'  => 'Holt stündlich neue globale Ankündigungen vom Hub.',
                'handler_type' => 'console',
                'handler'      => 'announcements:refresh',
                'schedule'     => '0 * * * *',
                'config'       => json_encode(['timeout' => 20]),
                'active'       => 1,
                'is_builtin'   => 1,
            ],
        ];

        foreach ($builtIns as $job) {
            $jobStmt->execute([
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
