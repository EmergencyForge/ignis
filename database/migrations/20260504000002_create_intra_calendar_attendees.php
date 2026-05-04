<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Kalender-Attendees: explizit eingeladene Mitarbeiter pro Event.
 * Wird NUR bei visibility='attendees' (manuell ausgewaehlt) und
 * visibility='private' (Organizer-Only) materialisiert. Bei
 * visibility='role' loest der AttendeeResolver dynamisch ueber die
 * Rolle auf — keine DB-Rows, sodass nachzuziehende Rollentraeger
 * automatisch in laufenden Serien drin sind.
 */
class CreateIntraCalendarAttendees extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_calendar_attendees')) {
            return;
        }

        $this->table('intra_calendar_attendees', ['id' => 'id', 'signed' => false])
            ->addColumn('event_id',       'integer',   ['signed' => false])
            ->addColumn('mitarbeiter_id', 'integer',   ['signed' => false])
            ->addColumn('response',       'enum',      ['values' => ['pending', 'accepted', 'declined', 'tentative'], 'default' => 'pending'])
            ->addColumn('responded_at',   'datetime',  ['null' => true])
            ->addColumn('is_organizer',   'boolean',   ['default' => false])
            ->addColumn('created_at',     'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['event_id', 'mitarbeiter_id'], ['unique' => true, 'name' => 'uniq_event_attendee'])
            ->addIndex(['mitarbeiter_id', 'response'], ['name' => 'idx_attendee_lookup'])
            ->addForeignKey('event_id',       'intra_calendar_events', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('mitarbeiter_id', 'intra_mitarbeiter',     'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
