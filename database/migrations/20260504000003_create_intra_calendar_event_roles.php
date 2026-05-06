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

        // Alte single-role-Spalte abraeumen, falls noch da. Schritt fuer
        // Schritt mit Lookup im information_schema, weil Phinx' dropForeignKey
        // je nach Version Probleme bei auto-generierten Constraint-Namen
        // macht. Lieber manuell per SQL — schlaegt nicht fehl wenn der FK
        // nicht (mehr) existiert.
        $events = $this->table('intra_calendar_events');
        if ($events->hasColumn('visibility_role_id')) {
            $constraintName = $this->fetchRow(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'intra_calendar_events'
                   AND COLUMN_NAME = 'visibility_role_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1"
            );
            if (!empty($constraintName['CONSTRAINT_NAME'])) {
                $name = $constraintName['CONSTRAINT_NAME'];
                $this->execute("ALTER TABLE `intra_calendar_events` DROP FOREIGN KEY `{$name}`");
            }
            // Index der ggf. zur FK existierte (Phinx erstellt automatisch
            // einen Index gleichen Namens) abraeumen, falls vorhanden.
            $indexRow = $this->fetchRow(
                "SELECT INDEX_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'intra_calendar_events'
                   AND COLUMN_NAME = 'visibility_role_id'
                 LIMIT 1"
            );
            if (!empty($indexRow['INDEX_NAME'])) {
                $idx = $indexRow['INDEX_NAME'];
                try {
                    $this->execute("ALTER TABLE `intra_calendar_events` DROP INDEX `{$idx}`");
                } catch (\Throwable) {
                    // Index-Drop kann fehlschlagen wenn der Index gleichzeitig
                    // ein Primary-Key wäre — passiert hier nicht, aber sicher
                    // ist sicher.
                }
            }
            // Spalte selbst entfernen
            $events->removeColumn('visibility_role_id')->update();
        }
    }
}
