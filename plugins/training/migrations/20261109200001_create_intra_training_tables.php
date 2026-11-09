<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tabellen der Ausbildung: Lehrgangsarten mit Lehrplan, Lehrgänge,
 * Teilnahmen, Fortschritt, Termine und Anwesenheit.
 *
 * Teilnahmen hängen am Mitarbeiter, nicht am Konto. Wird der Mitarbeiter
 * gelöscht, bleibt die Teilnahme ohne Person stehen (SET NULL), damit die
 * Zahlen eines Lehrgangs stimmen.
 *
 * Qualifikationen stehen als Text `rd:<id>`, `fw:<id>` oder `fd:<sgnr>`,
 * damit Voraussetzung und Ziel aus jedem Katalog kommen können.
 */
class CreateIntraTrainingTables extends AbstractMigration
{
    public function change(): void
    {
        $unsigned = ['id' => 'id', 'signed' => false];
        $ref      = ['signed' => false];

        if (!$this->hasTable('intra_training_course_types')) {
            $this->table('intra_training_course_types', $unsigned)
                ->addColumn('name', 'string', ['limit' => 150])
                ->addColumn('short', 'string', ['limit' => 20])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('area', 'string', ['limit' => 10, 'default' => 'rd'])
                ->addColumn('start_qualification', 'string', ['limit' => 40, 'null' => true])
                ->addColumn('pass_qualification', 'string', ['limit' => 40, 'null' => true])
                ->addColumn('validity_months', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('certificate_template_id', 'integer', ['null' => true])
                ->addColumn('signer_mode', 'string', ['limit' => 20, 'default' => 'lead'])
                ->addColumn('signer_user_id', 'integer', ['null' => true])
                ->addColumn('enrollment_mode', 'string', ['limit' => 20, 'default' => 'open'])
                ->addColumn('sequential', 'boolean', ['default' => false])
                ->addColumn('completion_plan', 'json', ['null' => true])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_prerequisites')) {
            $this->table('intra_training_prerequisites', $unsigned)
                ->addColumn('course_type_id', 'integer', $ref)
                ->addColumn('qualification', 'string', ['limit' => 40])
                ->addIndex(['course_type_id', 'qualification'], ['unique' => true])
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_courses')) {
            $this->table('intra_training_courses', $unsigned)
                ->addColumn('course_type_id', 'integer', $ref)
                ->addColumn('title', 'string', ['limit' => 150])
                ->addColumn('number', 'integer', ['signed' => false])
                ->addColumn('year', 'integer', ['signed' => false])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'planned'])
                ->addColumn('starts_on', 'date', ['null' => true])
                ->addColumn('ends_on', 'date', ['null' => true])
                ->addColumn('enroll_until', 'datetime', ['null' => true])
                ->addColumn('seats', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('lead_user_id', 'integer', ['null' => true])
                ->addColumn('created_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['course_type_id', 'year', 'number'], ['unique' => true])
                ->addIndex(['status'])
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'RESTRICT'])
                ->addForeignKey('lead_user_id', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_course_staff')) {
            $this->table('intra_training_course_staff', $unsigned)
                ->addColumn('course_id', 'integer', $ref)
                ->addColumn('user_id', 'integer')
                ->addColumn('role', 'string', ['limit' => 20, 'default' => 'instructor'])
                ->addIndex(['course_id', 'user_id', 'role'], ['unique' => true])
                ->addForeignKey('course_id', 'intra_training_courses', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_files')) {
            $this->table('intra_training_files', $unsigned)
                ->addColumn('storage_name', 'string', ['limit' => 80])
                ->addColumn('original_name', 'string', ['limit' => 255])
                ->addColumn('mime', 'string', ['limit' => 120])
                ->addColumn('size', 'integer', ['signed' => false])
                ->addColumn('version', 'integer', ['signed' => false, 'default' => 1])
                ->addColumn('pdf_storage_name', 'string', ['limit' => 80, 'null' => true])
                ->addColumn('convert_status', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('uploaded_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addForeignKey('uploaded_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_modules')) {
            // Ein Modul gehört zum Lehrplan einer Art oder als Zusatz zu
            // genau einem Lehrgang.
            $this->table('intra_training_modules', $unsigned)
                ->addColumn('course_type_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('course_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('position', 'integer', ['default' => 0])
                ->addColumn('title', 'string', ['limit' => 150])
                ->addTimestamps()
                ->addForeignKey('course_type_id', 'intra_training_course_types', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('course_id', 'intra_training_courses', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_units')) {
            $this->table('intra_training_units', $unsigned)
                ->addColumn('module_id', 'integer', $ref)
                ->addColumn('position', 'integer', ['default' => 0])
                ->addColumn('kind', 'string', ['limit' => 20])
                ->addColumn('title', 'string', ['limit' => 200])
                ->addColumn('required', 'boolean', ['default' => true])
                ->addColumn('file_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('content', 'json', ['null' => true])
                ->addColumn('url', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('kb_entry_id', 'integer', ['null' => true])
                ->addColumn('allow_download', 'boolean', ['default' => true])
                ->addColumn('check_exam_id', 'integer', ['signed' => false, 'null' => true])
                ->addTimestamps()
                ->addForeignKey('module_id', 'intra_training_modules', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('file_id', 'intra_training_files', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_sessions')) {
            // unit_id: der Termin plant die Lerneinheit „Termin“ des
            // Lehrplans für diesen Lehrgang ein.
            $this->table('intra_training_sessions', $unsigned)
                ->addColumn('course_id', 'integer', $ref)
                ->addColumn('module_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('unit_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('title', 'string', ['limit' => 200])
                ->addColumn('starts_at', 'datetime')
                ->addColumn('ends_at', 'datetime')
                ->addColumn('location', 'string', ['limit' => 150, 'null' => true])
                ->addColumn('required', 'boolean', ['default' => true])
                ->addColumn('calendar_event_id', 'integer', ['signed' => false, 'null' => true])
                ->addTimestamps()
                ->addIndex(['course_id', 'unit_id'], ['unique' => true])
                ->addForeignKey('course_id', 'intra_training_courses', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('module_id', 'intra_training_modules', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('unit_id', 'intra_training_units', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_training_participations')) {
            $this->table('intra_training_participations', $unsigned)
                ->addColumn('course_id', 'integer', $ref)
                // signed: intra_mitarbeiter.id ist signed int(11)
                ->addColumn('personnel_id', 'integer', ['null' => true])
                ->addColumn('status', 'string', ['limit' => 20])
                ->addColumn('waitlist_position', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('enrolled_by', 'integer', ['null' => true])
                ->addColumn('enrolled_at', 'datetime')
                ->addColumn('admitted_at', 'datetime', ['null' => true])
                ->addColumn('finished_at', 'datetime', ['null' => true])
                ->addColumn('result_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('grade', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('expires_on', 'date', ['null' => true])
                ->addColumn('start_qualification_before', 'string', ['limit' => 40, 'null' => true])
                ->addTimestamps()
                ->addIndex(['course_id', 'personnel_id'], ['unique' => true])
                ->addIndex(['personnel_id', 'status'])
                ->addForeignKey('course_id', 'intra_training_courses', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('personnel_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL'])
                ->addForeignKey('enrolled_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_unit_progress')) {
            $this->table('intra_training_unit_progress', $unsigned)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('unit_id', 'integer', $ref)
                ->addColumn('status', 'string', ['limit' => 10, 'default' => 'started'])
                ->addColumn('pages', 'json', ['null' => true])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addColumn('completed_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['participation_id', 'unit_id'], ['unique' => true])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('unit_id', 'intra_training_units', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('completed_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }

        if (!$this->hasTable('intra_training_attendance')) {
            $this->table('intra_training_attendance', $unsigned)
                ->addColumn('session_id', 'integer', $ref)
                ->addColumn('participation_id', 'integer', $ref)
                ->addColumn('state', 'string', ['limit' => 12])
                ->addColumn('recorded_by', 'integer', ['null' => true])
                ->addTimestamps()
                ->addIndex(['session_id', 'participation_id'], ['unique' => true])
                ->addForeignKey('session_id', 'intra_training_sessions', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('recorded_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }
    }
}
