<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\CalendarEvent;
use DateTimeInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

/**
 * Findet Termin-Konflikte fuer einen Mitarbeiter in einem Zeitraum.
 * Gedacht als nicht-blockierender Hint beim Erstellen/Aktualisieren von
 * Terminen — der Controller faengt die Liste, formatiert sie als
 * Flash-Warning und der User kann manuell entscheiden ob er trotzdem
 * speichert.
 *
 * Berücksichtigt:
 *  - Explizite Attendees (intra_calendar_attendees) — schneller Index-
 *    Lookup über mitarbeiter_id.
 *  - Recurring-Events: NICHT ausgeklappt. Wir prüfen nur die Master-Row,
 *    keine Vorkommen in der Range-Mitte. Praktisch reicht das für die
 *    Standardfälle (Konflikt mit einem Termin am gleichen Tag); volle
 *    Recurrence-Konflikt-Erkennung wäre eigene Phase.
 *
 *  Bewusst NICHT erfasst:
 *  - Visibility='all' (zaehlt nicht als persoenlicher Termin)
 *  - Visibility='role' ohne expliziten Attendee — siehe Doc-Block:
 *    role-getaggte Dienste haben oft mehrere hundert "implizite" Teil-
 *    nehmer und wuerden die Konflikt-Liste mit False-Positives fluten.
 */
final class ConflictDetector
{
    /**
     * @return Collection<int, object> Spalten: id, title, starts_at, ends_at
     */
    public static function findConflicts(
        int $mitarbeiterId,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $excludeEventId = null
    ): Collection {
        if ($mitarbeiterId <= 0) {
            return new Collection();
        }

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        $q = Capsule::table('intra_calendar_events as e')
            ->join('intra_calendar_attendees as a', 'a.event_id', '=', 'e.id')
            ->where('a.mitarbeiter_id', $mitarbeiterId)
            // Klassischer Overlap-Check: starts_at <= to AND ends_at >= from
            ->where('e.starts_at', '<=', $toStr)
            ->where('e.ends_at',   '>=', $fromStr)
            // Wir wollen nicht den eigenen Event als Konflikt zaehlen
            ->when($excludeEventId !== null, fn ($q) => $q->where('e.id', '!=', $excludeEventId))
            ->where('a.response', '!=', 'declined')
            ->select('e.id', 'e.title', 'e.starts_at', 'e.ends_at')
            ->orderBy('e.starts_at');

        return new Collection($q->get()->all());
    }

    /**
     * Bequemer aggregierter Check — gibt eine kurze Zusammenfassung
     * zurueck, geeignet fuer Flash::warning(). Leerer String wenn keine
     * Konflikte.
     */
    public static function describeConflictsForAttendees(
        array $mitarbeiterIds,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $excludeEventId = null
    ): string {
        $names = []; // mitarbeiter_id => fullname
        if ($mitarbeiterIds !== []) {
            $rows = Capsule::table('intra_mitarbeiter')
                ->whereIn('id', $mitarbeiterIds)
                ->select('id', 'fullname')
                ->get();
            foreach ($rows as $r) {
                $names[(int) $r->id] = (string) $r->fullname;
            }
        }

        $entries = [];
        foreach ($mitarbeiterIds as $mid) {
            $conflicts = self::findConflicts((int) $mid, $from, $to, $excludeEventId);
            if ($conflicts->isEmpty()) continue;

            $name = $names[(int) $mid] ?? ('Mitarbeiter #' . $mid);
            $titles = $conflicts->pluck('title')->take(3)->all();
            $more = $conflicts->count() - count($titles);
            $titleList = implode(', ', $titles) . ($more > 0 ? " (+{$more} weitere)" : '');
            $entries[] = "{$name}: {$titleList}";
        }

        if ($entries === []) return '';
        return 'Mögliche Konflikte: ' . implode(' | ', $entries);
    }
}
