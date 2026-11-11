<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Präsentation als Lerneinheit: Die Folien hängen an der Einheit, nicht mehr
 * am Termin.
 *
 * - `slides_after_talk` an der Einheit: ob Teilnehmer die Folien auch nach
 *   dem Vortrag sehen. Vorgabe ja, wie bisher das PDF im Lehrgang.
 * - `slides_file_id` am Termin entfällt. Ein Vortrag, der gerade läuft, wäre
 *   ohne Folien nicht fortzusetzen und endet deshalb mit seinem letzten
 *   Lebenszeichen. Die Dateien bleiben in `intra_training_files`.
 *
 * Daten ändert nur `up()`, `down()` stellt das Schema wieder her.
 */
class AddIntraTrainingPresentationUnits extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('DELETE e FROM intra_training_presentation_events e JOIN intra_training_presentations p ON p.id = e.presentation_id WHERE p.ended_at IS NULL');
        // Wie Presentations::end(): Zuschauer zählen höchstens die Dauer des Vortrags.
        $this->execute('UPDATE intra_training_presentation_viewers v JOIN intra_training_presentations p ON p.id = v.presentation_id SET v.seconds_watched = LEAST(v.seconds_watched, GREATEST(0, TIMESTAMPDIFF(SECOND, p.started_at, p.presenter_seen_at))) WHERE p.ended_at IS NULL');
        $this->execute("UPDATE intra_training_presentations SET ended_at = presenter_seen_at, handover_to = NULL, screen_via = NULL, screen_room = NULL, mode = 'slides' WHERE ended_at IS NULL");

        $this->table('intra_training_sessions')
            ->dropForeignKey('slides_file_id')
            ->save();
        $this->table('intra_training_sessions')
            ->removeColumn('slides_file_id')
            ->update();

        $this->table('intra_training_units')
            ->addColumn('slides_after_talk', 'boolean', ['default' => true, 'null' => false, 'after' => 'allow_download'])
            ->update();
    }

    public function down(): void
    {
        $this->table('intra_training_units')
            ->removeColumn('slides_after_talk')
            ->update();

        $this->table('intra_training_sessions')
            ->addColumn('slides_file_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'unit_id'])
            ->addForeignKey('slides_file_id', 'intra_training_files', 'id', ['delete' => 'SET_NULL'])
            ->update();
    }
}
