<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Live-Vortrag an Terminen: Folien (PDF) am Termin, je Termin mehrere
 * Vorträge mit Seite und Modus, Ereignisse für die Zuschauer (fortlaufende
 * `seq` je Vortrag) und die Zuschauer samt Zuschauzeit.
 *
 * `page` zählt ab 0, `mode` ist `slides`, `pause` oder `screen`. `seq` am
 * Vortrag ist die zuletzt vergebene Ereignisnummer, `handover_to` hält die
 * Übergabe an jemand anderen offen. Konten-Spalten sind vorzeichenbehaftet,
 * weil `intra_users.id` es ist.
 */
class AddIntraTrainingPresentations extends AbstractMigration
{
    public function change(): void
    {
        $unsigned = ['id' => 'id', 'signed' => false];
        $ref      = ['signed' => false];

        $this->table('intra_training_sessions')
            ->addColumn('slides_file_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'unit_id'])
            ->addForeignKey('slides_file_id', 'intra_training_files', 'id', ['delete' => 'SET_NULL'])
            ->update();

        $this->table('intra_training_presentations', $unsigned)
            ->addColumn('session_id', 'integer', $ref)
            ->addColumn('presenter_id', 'integer')
            ->addColumn('started_at', 'datetime')
            ->addColumn('ended_at', 'datetime', ['null' => true])
            ->addColumn('page', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('mode', 'string', ['limit' => 10, 'default' => 'slides'])
            ->addColumn('version', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('seq', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('presenter_seen_at', 'datetime')
            ->addColumn('handover_to', 'integer', ['null' => true])
            ->addIndex(['session_id', 'ended_at'])
            ->addForeignKey('session_id', 'intra_training_sessions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('presenter_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('handover_to', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('intra_training_presentation_events', $unsigned)
            ->addColumn('presentation_id', 'integer', $ref)
            ->addColumn('seq', 'integer', $ref)
            ->addColumn('kind', 'string', ['limit' => 10])
            ->addColumn('data', 'text')
            ->addColumn('created_at', 'datetime')
            ->addIndex(['presentation_id', 'seq'], ['unique' => true])
            ->addForeignKey('presentation_id', 'intra_training_presentations', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_training_presentation_viewers', $unsigned)
            ->addColumn('presentation_id', 'integer', $ref)
            ->addColumn('user_id', 'integer')
            ->addColumn('participation_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('first_seen_at', 'datetime')
            ->addColumn('last_seen_at', 'datetime')
            ->addColumn('seconds_watched', 'integer', ['signed' => false, 'default' => 0])
            ->addIndex(['presentation_id', 'user_id'], ['unique' => true])
            ->addForeignKey('presentation_id', 'intra_training_presentations', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('participation_id', 'intra_training_participations', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
