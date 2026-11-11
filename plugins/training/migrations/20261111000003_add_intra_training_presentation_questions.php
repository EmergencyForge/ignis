<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fragen und Besuche im Vortrag, ein Raum je Vortrag.
 *
 * - `room` ersetzt `screen_room`: Der Server würfelt den LiveKit-Raum einmal
 *   beim Start, nicht erst beim Teilen. `q_version` zählt Änderungen an den
 *   Fragen, damit die Abfrage sie bemerkt, ohne Seite und Modus anzufassen.
 * - Fragen leben nur während des Vortrags; das Ende löscht sie.
 * - Besuche bleiben mit dem Termin. Konten-Spalten sind vorzeichenbehaftet,
 *   weil `intra_users.id` es ist; mit dem Konto oder dem Vortrag gehen auch
 *   ihre Zeilen, wie bei den Zuschauern.
 *
 * Es ändert sich nur das Schema, `down()` stellt es wieder her.
 */
class AddIntraTrainingPresentationQuestions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_training_presentations')
            ->renameColumn('screen_room', 'room')
            ->update();
        $this->table('intra_training_presentations')
            ->addColumn('q_version', 'integer', ['signed' => false, 'default' => 0, 'after' => 'seq'])
            ->update();

        $unsigned = ['id' => 'id', 'signed' => false];
        $ref      = ['signed' => false];

        $this->table('intra_training_presentation_questions', $unsigned)
            ->addColumn('presentation_id', 'integer', $ref)
            ->addColumn('user_id', 'integer')
            ->addColumn('body', 'text')
            ->addColumn('asked_at', 'datetime')
            ->addColumn('answer', 'text', ['null' => true])
            ->addColumn('answered_by', 'integer', ['null' => true])
            ->addColumn('answered_at', 'datetime', ['null' => true])
            ->addColumn('done_at', 'datetime', ['null' => true])
            ->addIndex(['presentation_id', 'user_id'])
            ->addForeignKey('presentation_id', 'intra_training_presentations', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('answered_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('intra_training_presentation_visits', $unsigned)
            ->addColumn('presentation_id', 'integer', $ref)
            ->addColumn('user_id', 'integer')
            ->addColumn('joined_at', 'datetime')
            ->addColumn('last_seen_at', 'datetime')
            ->addColumn('left_at', 'datetime', ['null' => true])
            ->addIndex(['presentation_id', 'user_id', 'left_at'])
            ->addForeignKey('presentation_id', 'intra_training_presentations', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('intra_training_presentation_visits')->drop()->save();
        $this->table('intra_training_presentation_questions')->drop()->save();
        $this->table('intra_training_presentations')
            ->removeColumn('q_version')
            ->update();
        $this->table('intra_training_presentations')
            ->renameColumn('room', 'screen_room')
            ->update();
    }
}
