<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ab wann ein gescheiterter Abschluss wieder dran ist (training 0.6), leer
 * heißt sofort. Der Cron filtert danach, sonst belegen dauerhaft
 * scheiternde Abschlüsse das Limit und neuere laufen nie.
 */
class AddIntraTrainingCompletionsDueAt extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_training_completions')
            ->addColumn('due_at', 'datetime', ['null' => true, 'after' => 'finished'])
            ->addIndex(['finished', 'due_at'])
            ->update();
    }
}
