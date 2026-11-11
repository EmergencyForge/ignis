<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Bildschirm teilen im Vortrag.
 *
 * - `screen_via` ist `livekit` oder `direct`, `screen_room` der LiveKit-Raum;
 *   beide leer, solange nicht geteilt wird.
 * - `to_user_id` richtet ein Ereignis an genau einen Nutzer (Signale für den
 *   direkten Verbindungsaufbau); leer heißt für alle. Vorzeichenbehaftet,
 *   weil `intra_users.id` es ist.
 */
class AddIntraTrainingPresentationScreen extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_training_presentations')
            ->addColumn('screen_via', 'string', ['limit' => 10, 'null' => true, 'after' => 'mode'])
            ->addColumn('screen_room', 'string', ['limit' => 64, 'null' => true, 'after' => 'screen_via'])
            ->update();

        $this->table('intra_training_presentation_events')
            ->addColumn('to_user_id', 'integer', ['null' => true, 'after' => 'kind'])
            ->addIndex(['presentation_id', 'to_user_id'])
            ->addForeignKey('to_user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->update();
    }
}
