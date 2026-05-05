<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cron-Default fuer changelog:refresh.
 *
 * Lehnt sich an 20260424000004_seed_cron_defaults.php an. Wird alle 30 Minuten
 * ausgefuehrt — der Hub setzt Cache-Control max-age=600, also liefern viele
 * Refreshes nur 304 Not Modified, kein DB-Hit. Falls die Tabelle
 * intra_cron_jobs noch nicht existiert (frische Installation, andere
 * Migration noch nicht durch), wird einfach geskippt.
 */
class SeedChangelogCron extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_cron_jobs')) {
            return;
        }

        $pdo = $this->getAdapter()->getConnection();

        // INSERT IGNORE haelt bestehende Installs/Custom-Configs unangetastet —
        // wenn ein Job mit dem identifier schon existiert, passiert nichts.
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config,
                 active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, :handler_type, :handler, :schedule, :config,
                 :active, :is_builtin, NOW())'
        );
        $stmt->execute([
            'identifier'   => 'changelog.refresh',
            'name'         => 'Changelogs aktualisieren',
            'description'  => 'Holt alle 30 Minuten neue Changelog-Eintraege vom Hub.',
            'handler_type' => 'console',
            'handler'      => 'changelog:refresh',
            'schedule'     => '*/30 * * * *',
            'config'       => json_encode(['timeout' => 15]),
            'active'       => 1,
            'is_builtin'   => 1,
        ]);
    }

    public function down(): void
    {
        if (!$this->hasTable('intra_cron_jobs')) {
            return;
        }
        $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'changelog.refresh'");
    }
}
