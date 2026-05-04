<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\CalendarEvent;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Expandiert ein Recurring-Event in alle Vorkommen innerhalb eines
 * Zeitraums [from, to]. Akzeptiert ein Subset von RFC 5545 RRULE:
 *
 *   FREQ=DAILY|WEEKLY|MONTHLY
 *   INTERVAL=N
 *   BYDAY=MO,TU,WE,TH,FR,SA,SU   (nur fuer FREQ=WEEKLY)
 *   COUNT=N | UNTIL=YYYY-MM-DD[THH:MM:SS]
 *
 * Berücksichtigt Exception-Rows (Children mit parent_event_id = master.id und
 * recurrence_rule = NULL) — diese ueberschreiben das berechnete Vorkommen
 * am gleichen Datum.
 *
 * Returns array<CalendarEvent> — KOPIEN des Master-Events mit angepassten
 * starts_at/ends_at. Eltern-Event behaelt seine Original-Daten in der DB.
 */
final class RecurrenceExpander
{
    private const MAX_OCCURRENCES = 366; // Hard-Cap pro Expansion (1 Jahr taeglich)

    private const BYDAY_TO_PHP = [
        'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0,
    ];

    /**
     * @return array<int, CalendarEvent> Vorkommen sortiert nach starts_at
     */
    public static function expand(CalendarEvent $event, DateTimeInterface $from, DateTimeInterface $to): array
    {
        if (empty($event->recurrence_rule)) {
            return self::eventInRange($event, $from, $to) ? [$event] : [];
        }

        $rule = self::parseRule($event->recurrence_rule);
        if ($rule === null) {
            return [$event]; // unparseable → einmaliges Vorkommen
        }

        $tz        = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Berlin');
        $start     = self::toImmutable($event->starts_at, $tz);
        $end       = self::toImmutable($event->ends_at, $tz);
        $duration  = $end->getTimestamp() - $start->getTimestamp();
        $rangeFrom = self::toImmutable($from, $tz);
        $rangeTo   = self::toImmutable($to, $tz);

        // Hartes Ende der Serie: COUNT (in $rule['count']) oder UNTIL.
        $until = null;
        if (!empty($rule['until'])) {
            $until = new DateTimeImmutable($rule['until'], $tz);
        } elseif (!empty($event->recurrence_until)) {
            $until = self::toImmutable($event->recurrence_until, $tz);
        }
        $count = isset($rule['count']) ? (int) $rule['count'] : null;

        // Exceptions vorab laden — Map: 'YYYY-MM-DD' → CalendarEvent
        $exceptions = self::loadExceptions($event);

        $occurrences = [];
        $current     = $start;
        $i           = 0;

        while ($i < self::MAX_OCCURRENCES) {
            if ($until !== null && $current > $until) {
                break;
            }
            if ($count !== null && $i >= $count) {
                break;
            }

            // BYDAY-Filter (nur weekly): nur an erlaubten Wochentagen aufnehmen
            $isAllowedDay = empty($rule['byday'])
                || in_array(self::dayCode($current), $rule['byday'], true);

            if ($isAllowedDay && $current > $rangeTo) {
                break; // weiter nach hinten zu suchen ist sinnlos
            }

            if ($isAllowedDay) {
                $instanceEnd = $current->modify("+{$duration} seconds");
                $overlapsRange = $current <= $rangeTo && $instanceEnd >= $rangeFrom;

                if ($overlapsRange) {
                    $dateKey = $current->format('Y-m-d');
                    if (isset($exceptions[$dateKey])) {
                        // Exception ueberschreibt — kann auch DELETE sein
                        // (recurrence_rule = NULL, all_day = 1, title gleich master,
                        // starts_at = ends_at = master start = "geloescht")
                        // Wir behandeln Exceptions hier konservativ: einbinden.
                        $occurrences[] = $exceptions[$dateKey];
                    } else {
                        $clone = $event->replicate();
                        $clone->id              = $event->id; // gleiche ID, FullCalendar-Frontend
                        $clone->starts_at       = $current->format('Y-m-d H:i:s');
                        $clone->ends_at         = $instanceEnd->format('Y-m-d H:i:s');
                        $clone->recurrence_rule = null; // Instanz hat keine eigene Recurrence
                        $occurrences[] = $clone;
                    }
                }
                $i++;
            }

            $current = self::nextOccurrence($current, $rule);
        }

        usort($occurrences, fn ($a, $b) => strcmp((string) $a->starts_at, (string) $b->starts_at));
        return $occurrences;
    }

    /**
     * Parst eine RRULE-Subset-String zu einem Assoc-Array. NULL bei Parser-Fail.
     *
     * @return array{freq:string,interval:int,byday?:array,count?:int,until?:string}|null
     */
    private static function parseRule(string $rule): ?array
    {
        $parts = [];
        foreach (explode(';', $rule) as $segment) {
            if (str_contains($segment, '=')) {
                [$key, $val] = explode('=', $segment, 2);
                $parts[strtoupper(trim($key))] = trim($val);
            }
        }

        if (empty($parts['FREQ']) || !in_array($parts['FREQ'], ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            return null;
        }

        $out = [
            'freq'     => $parts['FREQ'],
            'interval' => max(1, (int) ($parts['INTERVAL'] ?? 1)),
        ];

        if (!empty($parts['BYDAY']) && $out['freq'] === 'WEEKLY') {
            $byday = [];
            foreach (explode(',', $parts['BYDAY']) as $code) {
                $code = strtoupper(trim($code));
                if (isset(self::BYDAY_TO_PHP[$code])) {
                    $byday[] = $code;
                }
            }
            if ($byday !== []) {
                $out['byday'] = $byday;
            }
        }

        if (!empty($parts['COUNT'])) {
            $out['count'] = max(1, (int) $parts['COUNT']);
        } elseif (!empty($parts['UNTIL'])) {
            $out['until'] = $parts['UNTIL'];
        }

        return $out;
    }

    private static function nextOccurrence(DateTimeImmutable $current, array $rule): DateTimeImmutable
    {
        $interval = $rule['interval'];

        return match ($rule['freq']) {
            'DAILY'   => $current->add(new DateInterval("P{$interval}D")),
            'WEEKLY'  => $current->add(new DateInterval("P1D")), // BYDAY filtert ab
            'MONTHLY' => $current->add(new DateInterval("P{$interval}M")),
            default   => $current->add(new DateInterval("P1D")),
        };
    }

    /**
     * Laedt Exception-Rows fuer ein Master-Event als YYYY-MM-DD-Map.
     */
    private static function loadExceptions(CalendarEvent $master): array
    {
        $map = [];
        try {
            $rows = CalendarEvent::query()
                ->where('parent_event_id', $master->id)
                ->whereNull('recurrence_rule')
                ->get();
            foreach ($rows as $row) {
                $key = $row->starts_at instanceof DateTimeInterface
                    ? $row->starts_at->format('Y-m-d')
                    : substr((string) $row->starts_at, 0, 10);
                $map[$key] = $row;
            }
        } catch (\Throwable) {
            // ignore — leere Map ist sicher
        }
        return $map;
    }

    /**
     * Wandelt eine Datum-Eingabe in DateTimeImmutable um.
     */
    private static function toImmutable(mixed $value, DateTimeZone $tz): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($tz);
        }
        return new DateTimeImmutable((string) $value, $tz);
    }

    private static function dayCode(DateTimeImmutable $dt): string
    {
        $codes = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
        return $codes[(int) $dt->format('w')];
    }

    private static function eventInRange(CalendarEvent $event, DateTimeInterface $from, DateTimeInterface $to): bool
    {
        $eventStart = (string) $event->starts_at;
        $eventEnd   = (string) $event->ends_at;
        return strcmp($eventStart, $to->format('Y-m-d H:i:s')) <= 0
            && strcmp($eventEnd, $from->format('Y-m-d H:i:s')) >= 0;
    }
}
