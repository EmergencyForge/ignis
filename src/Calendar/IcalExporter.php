<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\CalendarEvent;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Generiert ein RFC-5545-konformes iCal-Dokument fuer einen User. Wird
 * via tokenbasiertem Endpoint /api/kalender/ical/{token} ausgeliefert
 * — externe Kalender (Google, Apple, Outlook) refreshen periodisch.
 *
 * Range: 6 Monate rueckwaerts, 12 Monate vorwaerts. Recurring-Events
 * werden NICHT expandiert — wir exportieren das Master-Event mit RRULE
 * und ueberlassen dem importierenden Kalender die Expansion (Standard
 * iCal-Behavior).
 *
 * Sichtbarkeit: derselbe scopeVisibleTo-Filter wie in eventsJson — der
 * User sieht nur Events die er auch im Web-UI sehen wuerde.
 */
final class IcalExporter
{
    private const PRODID = '-//ıgnıs intraRP//Calendar//DE';
    private const RANGE_BACK_MONTHS = 6;
    private const RANGE_FWD_MONTHS  = 12;

    public static function export(int $userId): string
    {
        $user = Capsule::table('intra_users')
            ->where('id', $userId)
            ->first(['id', 'role', 'cirs_username', 'discord_id']);
        if ($user === null) {
            return self::wrapEmpty();
        }

        $roleId        = $user->role !== null ? (int) $user->role : null;
        $mitarbeiterId = self::resolveMitarbeiterId((string) ($user->discord_id ?? ''));

        $from = (new DateTimeImmutable('today'))->modify('-' . self::RANGE_BACK_MONTHS . ' months');
        $to   = (new DateTimeImmutable('today'))->modify('+' . self::RANGE_FWD_MONTHS . ' months');

        $events = CalendarEvent::query()
            ->visibleTo($userId, $roleId, $mitarbeiterId)
            ->inRange($from, $to)
            ->where(function ($q) {
                $q->whereNull('parent_event_id')->orWhereNotNull('recurrence_rule');
            })
            ->get();

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:' . self::PRODID;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $calName = 'ıgnıs Kalender — ' . ($user->cirs_username ?? '');
        $lines[] = 'X-WR-CALNAME:' . self::escapeText($calName);
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';

        foreach ($events as $event) {
            foreach (self::renderEvent($event) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545: lines must use CRLF + be ≤ 75 octets (mit Continuation)
        return self::foldAndJoin($lines);
    }

    /**
     * @return string[] iCal-Zeilen fuer ein einzelnes Event
     */
    private static function renderEvent(CalendarEvent $event): array
    {
        $allDay = (bool) $event->all_day;
        $start  = self::toDateTime($event->starts_at);
        $end    = self::toDateTime($event->ends_at);
        if ($start === null || $end === null) return [];

        $uid = 'cal-' . $event->id . '@ignis-intrarp';
        $now = (new DateTimeImmutable())->format('Ymd\THis\Z');

        $out = [];
        $out[] = 'BEGIN:VEVENT';
        $out[] = 'UID:' . $uid;
        $out[] = 'DTSTAMP:' . $now;

        if ($allDay) {
            // VALUE=DATE: Start ist tag, Ende exklusiv (Standard-iCal-Semantik).
            // Wir speichern inklusiv → +1 Tag fuers iCal-Format.
            $endExclusive = $end->modify('+1 day');
            $out[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $out[] = 'DTEND;VALUE=DATE:'   . $endExclusive->format('Ymd');
        } else {
            // Lokale Zeit ohne TZID — Importing-Apps interpretieren als
            // Floating-Time (oder Server-Zeit) — pragmatisch fuer den Use-Case.
            $out[] = 'DTSTART:' . $start->format('Ymd\THis');
            $out[] = 'DTEND:'   . $end->format('Ymd\THis');
        }

        $out[] = 'SUMMARY:' . self::escapeText((string) $event->title);

        if (!empty($event->description)) {
            $out[] = 'DESCRIPTION:' . self::escapeText((string) $event->description);
        }
        if (!empty($event->location)) {
            $out[] = 'LOCATION:' . self::escapeText((string) $event->location);
        }

        // Recurrence — unser RRULE-Subset ist 1:1 RFC-5545-konform.
        if (!empty($event->recurrence_rule)) {
            $rrule = (string) $event->recurrence_rule;
            if (!empty($event->recurrence_until)) {
                $until = self::toDateTime($event->recurrence_until);
                if ($until !== null && !str_contains($rrule, 'UNTIL=') && !str_contains($rrule, 'COUNT=')) {
                    $rrule .= ';UNTIL=' . $until->format('Ymd\THis\Z');
                }
            }
            $out[] = 'RRULE:' . $rrule;
        }

        // Kategorie als CATEGORIES-Property — Apple/Google Calendar zeigt sie.
        if (!empty($event->category)) {
            $out[] = 'CATEGORIES:' . strtoupper(self::escapeText((string) $event->category));
        }

        $out[] = 'END:VEVENT';
        return $out;
    }

    private static function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) return $value;
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if ($value === null || $value === '') return null;
        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * RFC 5545: TEXT-Werte mit \\, \;, \,, \n als escape-Sequenzen.
     */
    private static function escapeText(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value
        );
    }

    /**
     * RFC 5545 line-folding: Zeilen ueber 75 Oktetten werden mit CRLF +
     * Leerzeichen-Continuation umgebrochen. Joint mit CRLF und finalem CRLF.
     */
    private static function foldAndJoin(array $lines): string
    {
        $folded = [];
        foreach ($lines as $line) {
            $bytes = strlen($line);
            if ($bytes <= 75) {
                $folded[] = $line;
                continue;
            }
            // 75 Oktetten pro Zeile, Continuation-Zeilen bekommen ein Space-Prefix
            $first = substr($line, 0, 75);
            $rest  = substr($line, 75);
            $folded[] = $first;
            while ($rest !== false && strlen($rest) > 0) {
                $chunk = substr($rest, 0, 74);
                $folded[] = ' ' . $chunk;
                $rest = substr($rest, 74);
            }
        }
        return implode("\r\n", $folded) . "\r\n";
    }

    private static function wrapEmpty(): string
    {
        return self::foldAndJoin([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'END:VCALENDAR',
        ]);
    }

    private static function resolveMitarbeiterId(string $discordId): ?int
    {
        if ($discordId === '') return null;
        $id = Capsule::table('intra_mitarbeiter')
            ->where('discordtag', $discordId)
            ->value('id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * Generiert oder holt den persoenlichen iCal-Token eines Users.
     * Idempotent: ein einmal vergebener Token wird wiederverwendet, sodass
     * URLs in externen Calendars stabil bleiben.
     */
    public static function ensureToken(int $userId): string
    {
        $existing = Capsule::table('intra_users')
            ->where('id', $userId)
            ->value('ical_token');
        if ($existing !== null && $existing !== '') {
            return (string) $existing;
        }
        $token = bin2hex(random_bytes(24)); // 48 Hex-Zeichen
        Capsule::table('intra_users')
            ->where('id', $userId)
            ->update(['ical_token' => $token]);
        return $token;
    }

    public static function regenerateToken(int $userId): string
    {
        $token = bin2hex(random_bytes(24));
        Capsule::table('intra_users')
            ->where('id', $userId)
            ->update(['ical_token' => $token]);
        return $token;
    }
}
