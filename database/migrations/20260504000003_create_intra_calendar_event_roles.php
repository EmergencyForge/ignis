<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erweitert die Calendar-Visibility um Multi-Role-Support: ein Event kann
 * jetzt mehrere Rollen als sichtbarkeits-allowed_list haben (z.B. eine
 * Übungsdienst-Termin fuer Aktive *und* Probezeit). Loest die single
 * `visibility_role_id`-Spalte ab.
 *
 * Frueh (Dev-Phase) eingefuehrt — keine produktive Daten-Migration noetig.
 */
class CreateIntraCalendarEventRoles extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_calendar_event_roles')) {
            $this->table('intra_calendar_event_roles', ['id' => 'id', 'signed' => false])
                ->addColumn('event_id', 'integer', ['signed' => false])
                ->addColumn('role_id',  'integer', ['signed' => false])
                ->addIndex(['event_id', 'role_id'], ['unique' => true, 'name' => 'uniq_event_role'])
                ->addIndex(['role_id'], ['name' => 'idx_event_role_lookup'])
                ->addForeignKey('event_id', 'intra_calendar_events', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('role_id',  'intra_users_roles',     'id', ['delete' => 'CASCADE'])
                ->create();
        }

        // Single-Role-Spalte ist jetzt redundant. FK zuerst droppen, dann Spalte.
        $events = $this->table('intra_calendar_events');
        if ($events->hasColumn('visibility_role_id')) {
            try {
                $events->dropForeignKey('visibility_role_id')->update();
            } catch (\Throwable) {
                // FK-Name unbekannt → manuell per Rohem SQL droppen
                $this->execute("ALTER TABLE `intra_calendar_events` DROP FOREIGN KEY IF EXISTS `intra_calendar_events_ibfk_2`");
            }
            $events->removeColumn('visibility_role_id')->update();
        }
    }
}
