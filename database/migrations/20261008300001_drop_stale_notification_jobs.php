<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Räumt den Rückstau der Queue `notifications` ab.
 *
 * Der Worker (`queue:work`, Cron-Eintrag `queue.work`) hat bisher nur die
 * Queue `default` abgearbeitet. Was auf `notifications` landete, vor allem
 * die Discord-Webhooks zu freigegebenen eNOTF- und fireTab-Protokollen und
 * zu Voranmeldungen, blieb liegen. Jetzt arbeitet er beide Queues ab; ohne
 * diese Migration gingen beim ersten Lauf alle alten Nachrichten auf
 * einmal raus. Jobs der letzten Stunde bleiben und werden noch zugestellt.
 */
final class DropStaleNotificationJobs extends AbstractMigration
{
    private const KEEP_SECONDS = 3600;

    public function up(): void
    {
        if (!$this->hasTable('intra_jobs')) {
            return;
        }

        $this->execute(sprintf(
            "DELETE FROM intra_jobs WHERE queue = 'notifications' AND reserved_at IS NULL AND created_at < %d",
            time() - self::KEEP_SECONDS,
        ));
    }

    public function down(): void
    {
        // Gelöschte Jobs lassen sich nicht zurückholen.
    }
}
