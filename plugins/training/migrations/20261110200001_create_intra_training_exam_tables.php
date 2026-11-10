<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Prüfungen: Fragenpool mit Themen, Prüfungsteile einer Lehrgangsart,
 * Freigaben, Versuche mit Antworten und Zusatzversuche.
 *
 * Eine Frage behält ihre ID über alle Versionen, damit Prüfungsordnungen
 * auf sie zeigen können. Die alte Fassung wandert beim Speichern in eine
 * eigene, weich gelöschte Zeile; `previous_id` der aktuellen Zeile zeigt
 * dorthin. Versuche speichern ihre Fragen ohnehin als Kopie in `drawn`.
 */
class CreateIntraTrainingExamTables extends AbstractMigration
{
    public function change(): void
    {
        $unsigned = ['id' => 'id', 'signed' => false];
        $ref      = ['signed' => false];

        if (!$this->hasTable('intra_training_topics')) {
            $this->table('intra_training_topics', $unsigned)
                ->addColumn('course_type_id', 'integer', $ref)
                ->addColumn('position', 'integer', ['default' => 0])
                ->addColumn('name', 'string', ['limit' => 150])
                ->addTimestamps()
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_questions')) {
            $this->table('intra_training_questions', $unsigned)
                ->addColumn('course_type_id', 'integer', $ref)
                ->addColumn('topic_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('type', 'string', ['limit' => 20])
                ->addColumn('body', 'text')
                ->addColumn('image_path', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('points', 'decimal', ['precision' => 6, 'scale' => 2, 'default' => 1])
                ->addColumn('spec', 'json', ['null' => true])
                ->addColumn('explanation', 'text', ['null' => true])
                ->addColumn('version', 'integer', ['signed' => false, 'default' => 1])
                ->addColumn('previous_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('deleted_at', 'datetime', ['null' => true])
                ->addColumn('created_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['course_type_id', 'deleted_at'])
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('topic_id', 'intra_training_topics', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('previous_id', 'intra_training_questions', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_exams')) {
            $this->table('intra_training_exams', $unsigned)
                ->addColumn('course_type_id', 'integer', $ref)
                ->addColumn('position', 'integer', ['default' => 0])
                ->addColumn('kind', 'string', ['limit' => 12])
                ->addColumn('title', 'string', ['limit' => 150])
                ->addColumn('required', 'boolean', ['default' => true])
                ->addColumn('blueprint', 'json', ['null' => true])
                ->addColumn('sheet', 'json', ['null' => true])
                ->addColumn('settings', 'json', ['null' => true])
                ->addTimestamps()
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_exam_releases')) {
            // Ohne participation_id gilt die Freigabe für den ganzen Lehrgang.
            $this->table('intra_training_exam_releases', $unsigned)
                ->addColumn('exam_id', 'integer', $ref)
                ->addColumn('course_id', 'integer', $ref)
                ->addColumn('participation_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('released_by', 'integer', ['null' => true])
                ->addColumn('released_at', 'datetime')
                ->addIndex(['exam_id', 'course_id'])
                ->addForeignKey('exam_id', 'intra_training_exams', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('course_id', 'intra_training_courses', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('released_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_attempts')) {
            $this->table('intra_training_attempts', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('exam_id', 'integer', $ref)
                ->addColumn('number', 'integer', ['signed' => false])
                ->addColumn('seed', 'biginteger')
                ->addColumn('drawn', 'json', ['null' => true])
                ->addColumn('started_at', 'datetime')
                ->addColumn('deadline_at', 'datetime', ['null' => true])
                ->addColumn('submitted_at', 'datetime', ['null' => true])
                ->addColumn('status', 'string', ['limit' => 12, 'default' => 'open'])
                ->addColumn('points', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
                ->addColumn('max_points', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
                ->addColumn('percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('passed', 'boolean', ['null' => true])
                ->addColumn('focus_lost', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('graded_by', 'integer', ['null' => true])
                ->addColumn('cosigned_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['participation_id', 'exam_id', 'number'], ['unique' => true])
                ->addIndex(['status', 'deadline_at'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('exam_id', 'intra_training_exams', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('graded_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('cosigned_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_attempt_answers')) {
            // question_id ohne Fremdschlüssel: der Versuch bleibt lesbar,
            // auch wenn die Frage längst weg ist; Wortlaut steht in drawn.
            $this->table('intra_training_attempt_answers', $unsigned)
                ->addColumn('attempt_id', 'integer', $ref)
                ->addColumn('position', 'integer', ['signed' => false])
                ->addColumn('question_id', 'integer', ['signed' => false])
                ->addColumn('question_version', 'integer', ['signed' => false])
                ->addColumn('answer', 'json', ['null' => true])
                ->addColumn('auto_points', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true])
                ->addColumn('manual_points', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true])
                ->addColumn('needs_review', 'boolean', ['default' => false])
                ->addColumn('comment', 'text', ['null' => true])
                ->addColumn('graded_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['attempt_id', 'position'], ['unique' => true])
                ->addIndex(['question_id', 'question_version'])
                ->addForeignKey('attempt_id', 'intra_training_attempts', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('graded_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_extra_attempts')) {
            $this->table('intra_training_extra_attempts', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('exam_id', 'integer', $ref)
                ->addColumn('reason', 'string', ['limit' => 500])
                ->addColumn('granted_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['participation_id', 'exam_id'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('exam_id', 'intra_training_exams', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('granted_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }
    }
}
