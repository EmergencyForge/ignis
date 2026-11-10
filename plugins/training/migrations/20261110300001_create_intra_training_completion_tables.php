<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Abschluss: eine Zeile je Teilnahme mit dem Stand der Abschluss-Kette als
 * JSON (`Completion::toArray()` aus dem Paket), dazu die Einstellungen der
 * Schritte an der Lehrgangsart, Bekanntgaben fürs Dashboard und
 * Folgeaufgaben.
 *
 * `document_id` steht neben dem JSON, weil der Urkunden-Schritt den Entwurf
 * schon vor dem Ausstellen anlegt. Scheitert das PDF, nimmt der nächste
 * Versuch denselben Entwurf statt einen zweiten anzulegen.
 */
class CreateIntraTrainingCompletionTables extends AbstractMigration
{
    public function change(): void
    {
        $unsigned = ['id' => 'id', 'signed' => false];
        $ref      = ['signed' => false];

        if (!$this->table('intra_training_course_types')->hasColumn('completion_settings')) {
            $this->table('intra_training_course_types')
                ->addColumn('completion_settings', 'json', ['null' => true, 'after' => 'completion_plan'])
                ->update();
        }

        if (!$this->hasTable('intra_training_completions')) {
            $this->table('intra_training_completions', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('passed', 'boolean')
                ->addColumn('state', 'json')
                ->addColumn('finished', 'boolean', ['default' => false])
                ->addColumn('document_id', 'integer', ['signed' => false, 'null' => true])
                ->addTimestamps()
                ->addIndex(['participation_id'], ['unique' => true])
                ->addIndex(['finished', 'id'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('document_id', 'intra_documents', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_announcements')) {
            $this->table('intra_training_announcements', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('text', 'string', ['limit' => 500])
                ->addColumn('expires_at', 'datetime')
                ->addTimestamps()
                ->addIndex(['participation_id'], ['unique' => true])
                ->addIndex(['expires_at'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_follow_ups')) {
            // Entweder an ein Konto oder an alle Konten einer Rolle.
            $this->table('intra_training_follow_ups', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('personnel_id', 'integer', ['null' => true])
                ->addColumn('user_id', 'integer', ['null' => true])
                ->addColumn('role_id', 'integer', ['null' => true])
                ->addColumn('title', 'string', ['limit' => 200])
                ->addColumn('due_on', 'date', ['null' => true])
                ->addColumn('done_at', 'datetime', ['null' => true])
                ->addColumn('done_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['participation_id'], ['unique' => true])
                ->addIndex(['done_at'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('personnel_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('done_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }
    }
}
