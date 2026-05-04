<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * track_attendance: Opt-In fuer rollen-getaggte Events.
 *
 * Bei visibility='role' wird die Teilnehmer-Liste mit RSVP standardmaessig
 * NICHT angezeigt — bei einer Serie wie "Uebungsdienst jeden Mittwoch fuer
 * alle Aktive" interessiert niemanden eine Liste mit 50 implizit eingeladenen
 * Personen. Setzt der Ersteller dieses Flag explizit, schaltet das Frontend
 * die RSVP-Buttons fuer Rollenmitglieder frei.
 *
 * Fuer visibility='attendees' und 'private' ist das Flag bedeutungslos —
 * dort gibt es immer eine Teilnehmer-Liste.
 */
class AddTrackAttendanceToIntraCalendarEvents extends AbstractMigration
{
    public function change(): void
    {
        $events = $this->table('intra_calendar_events');
        if (!$events->hasColumn('track_attendance')) {
            $events
                ->addColumn('track_attendance', 'boolean', [
                    'default' => false,
                    'after'   => 'visibility',
                ])
                ->update();
        }
    }
}
