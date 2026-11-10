<?php

declare(strict_types=1);

use App\Documents\Editor\SystemTemplates;
use Phinx\Migration\AbstractMigration;

/**
 * Erinnerung vor Ablauf (`training:expiry`, täglich) und Umwandlung von
 * PowerPoint in PDF (`training:convert`, jede Minute; ohne eingerichteten
 * Umwandler endet der Lauf sofort). `expiry_notified_at` hält fest, dass
 * eine Teilnahme ihre Erinnerung schon bekommen hat. Dazu der Prüfcode an
 * den mitgelieferten Lehrgangsurkunden.
 */
final class AddIntraTrainingExpiryAndConvert extends AbstractMigration
{
    private const JOBS = [
        [
            'identifier'  => 'training.expiry',
            'name'        => 'Ablauf von Lehrgängen melden',
            'description' => 'Meldet 30 Tage vor Ablauf eines bestandenen Lehrgangs an die Person und die Lehrgangsleitung, mit dem nächsten offenen Lehrgang derselben Art.',
            'handler'     => 'training:expiry',
            'schedule'    => '0 7 * * *',
        ],
        [
            'identifier'  => 'training.convert',
            'name'        => 'PowerPoint in PDF umwandeln',
            'description' => 'Wandelt hochgeladene PowerPoint-Dateien mit LibreOffice in PDF um, wenn TRAINING_CONVERTER gesetzt ist.',
            'handler'     => 'training:convert',
            'schedule'    => '* * * * *',
        ],
    ];

    public function up(): void
    {
        $this->table('intra_training_participations')
            ->addColumn('expiry_notified_at', 'datetime', ['null' => true, 'after' => 'expires_on'])
            ->update();

        // Die mitgelieferten Lehrgangsurkunden drucken den Prüfcode ab Werk, eigene Vorlagen nicht.
        $this->getAdapter()->getConnection()
            ->prepare('UPDATE intra_document_templates SET print_verification = 1 WHERE name IN (?, ?)')
            ->execute([SystemTemplates::TRAINING_DIPLOMA, SystemTemplates::TRAINING_COURSE]);

        if (!$this->hasTable('intra_cron_jobs')) {
            return;
        }
        $stmt = $this->getAdapter()->getConnection()->prepare(
            'INSERT IGNORE INTO intra_cron_jobs
                (identifier, name, description, handler_type, handler, schedule, config, active, is_builtin, next_run_at)
             VALUES
                (:identifier, :name, :description, \'console\', :handler, :schedule, :config, 1, 1, NOW())'
        );
        foreach (self::JOBS as $job) {
            $stmt->execute($job + ['config' => json_encode(['timeout' => 300])]);
        }
    }

    public function down(): void
    {
        if ($this->hasTable('intra_cron_jobs')) {
            $this->execute("DELETE FROM intra_cron_jobs WHERE identifier IN ('training.expiry', 'training.convert')");
        }
        $this->table('intra_training_participations')->removeColumn('expiry_notified_at')->update();
    }
}
