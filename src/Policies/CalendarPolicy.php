<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\CalendarEvent;

/**
 * CalendarPolicy — wer darf was mit Kalender-Events.
 *
 * Sichtbarkeitslogik in view():
 *   - Ersteller: immer
 *   - visibility='all'        → jeder mit calendar.view
 *   - visibility='role'       → User hat Match auf event.visibility_role_id
 *   - visibility='attendees'  → User ist via Mitarbeiter in attendees
 *   - visibility='private'    → nur Ersteller (oder admin/calendar.manage)
 *
 * Update/Delete: Ersteller ODER admin/calendar.manage.
 */
class CalendarPolicy
{
    public static function viewAny(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'calendar.view']);
    }

    public static function view(?CalendarEvent $event = null): bool
    {
        if (!Permissions::check(['admin', 'calendar.view'])) {
            return false;
        }

        if ($event === null) {
            return true;
        }

        $userId = (int) ($_SESSION['userid'] ?? 0);

        // Ersteller darf immer
        if ($userId > 0 && (int) $event->created_by === $userId) {
            return true;
        }

        // Admin-Override
        if (Permissions::check(['admin', 'calendar.manage'])) {
            return true;
        }

        switch ($event->visibility) {
            case CalendarEvent::VISIBILITY_ALL:
                return true;

            case CalendarEvent::VISIBILITY_ROLE:
                $userRoleId = (int) ($_SESSION['role_id'] ?? 0);
                return $userRoleId > 0 && $userRoleId === (int) $event->visibility_role_id;

            case CalendarEvent::VISIBILITY_ATTENDEES:
                $mitarbeiterId = self::resolveMitarbeiterId();
                if ($mitarbeiterId === null) {
                    return false;
                }
                return $event->attendees()
                    ->where('mitarbeiter_id', $mitarbeiterId)
                    ->exists();

            case CalendarEvent::VISIBILITY_PRIVATE:
            default:
                return false;
        }
    }

    public static function create(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'calendar.create']);
    }

    public static function update(?CalendarEvent $event = null): bool
    {
        if (!isset($_SESSION['userid'])) {
            return false;
        }

        if ($event === null) {
            return self::create();
        }

        $userId = (int) $_SESSION['userid'];
        if ((int) $event->created_by === $userId) {
            return true;
        }

        return Permissions::check(['admin', 'calendar.manage']);
    }

    public static function delete(?CalendarEvent $event = null): bool
    {
        return self::update($event);
    }

    /**
     * Resolved den Mitarbeiter-Datensatz, der zum aktuell eingeloggten User
     * gehoert (via discord_id <-> discordtag), und gibt seine ID zurueck.
     * Liegt in der Session-Persona kein Mitarbeiter, gibt's null.
     */
    private static function resolveMitarbeiterId(): ?int
    {
        // SessionManager kennt user_id; Mitarbeiter-Lookup erfolgt einmal pro
        // Request via Cached-Lookup im SessionManager. Falls nicht verfuegbar,
        // direkter DB-Lookup.
        if (isset($_SESSION['mitarbeiter_id']) && (int) $_SESSION['mitarbeiter_id'] > 0) {
            return (int) $_SESSION['mitarbeiter_id'];
        }

        $discordId = $_SESSION['discordtag'] ?? null;
        if (!$discordId) {
            return null;
        }

        try {
            $row = \App\Models\Mitarbeiter::query()
                ->where('discordtag', $discordId)
                ->first(['id']);
            return $row ? (int) $row->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
