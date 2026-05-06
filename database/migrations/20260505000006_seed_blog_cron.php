<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cron-Default fuer blog:refresh.
 *
 * Schwester-Job zu changelog.refresh (Migration 20260505000002).
 * Alle 30 Minuten — Hub liefert dank ETag/Last-Modified-Support oft 304,
 * also kein DB-Hit auf der anderen Seite. Stehen `intra_cron_jobs` noch
 * nicht zur Verfuegung, wird die Migration geskippt.
 */
class SeedBlogCron extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_cron_jobs')) {
            return;
        }

        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config,
                 active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, :handler_type, :handler, :schedule, :config,
                 :active, :is_builtin, NOW())'
        );
        $stmt->execute([
            'identifier'   => 'blog.refresh',
            'name'         => 'Blog-Posts aktualisieren',
            'description'  => 'Holt alle 30 Minuten neue Blog-Posts vom Hub.',
            'handler_type' => 'console',
            'handler'      => 'blog:refresh',
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
        $this->execute("DELETE FROM intra_cron_jobs WHERE identifier = 'blog.refresh'");
    }
}
