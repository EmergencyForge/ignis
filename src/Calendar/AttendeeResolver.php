<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\CalendarAttendee;
use App\Models\CalendarEvent;
use App\Models\Mitarbeiter;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

/**
 * Loest die effektive Attendee-Liste eines Events auf — egal ob die
 * Attendees explizit gepflegt sind (visibility='attendees', 'private') oder
 * dynamisch aus einer Rolle kommen (visibility='role').
 *
 * Bei visibility='role' werden NICHT in `intra_calendar_attendees`
 * persistiert, weil das bei einer Serie mit hunderten Vorkommen + sich
 * aendernden Rollentraegern brutal Daten produzieren wuerde. Stattdessen
 * resolven wir bei jedem Render und Notify dynamisch.
 */
final class AttendeeResolver
{
    /**
     * Mitarbeiter, die am Event teilnehmen / fuer die das Event sichtbar ist.
     *
     * @return Collection<int, Mitarbeiter>
     */
    public static function resolve(CalendarEvent $event): Collection
    {
        return match ($event->visibility) {
            CalendarEvent::VISIBILITY_ATTENDEES, CalendarEvent::VISIBILITY_PRIVATE
                => self::resolveExplicit($event),
            CalendarEvent::VISIBILITY_ROLE
                => self::resolveByRole((int) $event->visibility_role_id),
            CalendarEvent::VISIBILITY_ALL
                => new Collection(),
            default
                => new Collection(),
        };
    }

    /**
     * User-IDs (intra_users.id) der Personen, die fuer dieses Event eine
     * Notification bekommen sollen. Nutzt die Mitarbeiter-Liste aus resolve()
     * und joint via discordtag <-> discord_id.
     *
     * @return array<int>
     */
    public static function resolveUserIds(CalendarEvent $event): array
    {
        $mitarbeiter = self::resolve($event);
        if ($mitarbeiter->isEmpty()) {
            return [];
        }

        $discordTags = $mitarbeiter->pluck('discordtag')->filter()->unique()->values()->all();
        if ($discordTags === []) {
            return [];
        }

        $rows = Capsule::table('intra_users')
            ->whereIn('discord_id', $discordTags)
            ->pluck('id')
            ->all();

        return array_map('intval', $rows);
    }

    /**
     * Anzahl der Attendees — fuer FullCalendar's extendedProps.attendeeCount.
     * Schneller als resolve()->count(), weil bei Role-Visibility nur ein
     * COUNT(*) abgesetzt wird.
     */
    public static function count(CalendarEvent $event): int
    {
        return match ($event->visibility) {
            CalendarEvent::VISIBILITY_ATTENDEES, CalendarEvent::VISIBILITY_PRIVATE
                => CalendarAttendee::where('event_id', $event->id)->count(),
            CalendarEvent::VISIBILITY_ROLE
                => self::countByRole((int) $event->visibility_role_id),
            CalendarEvent::VISIBILITY_ALL
                => 0, // "alle" ist konzeptionell unbeschraenkt — Frontend zeigt das nicht
            default
                => 0,
        };
    }

    private static function resolveExplicit(CalendarEvent $event): Collection
    {
        $ids = CalendarAttendee::where('event_id', $event->id)
            ->pluck('mitarbeiter_id')
            ->all();
        if ($ids === []) {
            return new Collection();
        }
        return Mitarbeiter::query()
            ->whereIn('id', $ids)
            ->get();
    }

    private static function resolveByRole(int $roleId): Collection
    {
        if ($roleId <= 0) {
            return new Collection();
        }
        $discordIds = Capsule::table('intra_users')
            ->where('role', $roleId)
            ->pluck('discord_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($discordIds === []) {
            return new Collection();
        }

        return Mitarbeiter::query()
            ->whereIn('discordtag', $discordIds)
            ->get();
    }

    private static function countByRole(int $roleId): int
    {
        if ($roleId <= 0) {
            return 0;
        }
        return (int) Capsule::table('intra_users as u')
            ->join('intra_mitarbeiter as m', 'm.discordtag', '=', 'u.discord_id')
            ->where('u.role', $roleId)
            ->count();
    }
}
