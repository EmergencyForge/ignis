<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Zentraler Zeit-Formatter.
 *
 * Zwei Input-Varianten:
 *
 * 1. **UTC-Input** (`formatShort`, `formatLong`, `formatTime`, `formatDate`):
 *    DB-Wert wird als UTC gelesen und nach `Europe/Berlin` konvertiert.
 *    Anwendbar auf Felder, die mit `UTC_TIMESTAMP()` geschrieben werden
 *    (neuere Tabellen: `intra_cron_jobs`, `intra_cron_runs`, …).
 *
 * 2. **Local-Input** (`formatShortLocal`, `formatLongLocal`, …):
 *    DB-Wert wird in der PHP-Default-Timezone gelesen und 1:1 formatiert.
 *    Semantisch identisch zu `date('d.m.Y H:i', strtotime($x))` — die
 *    Variante für historische Felder, die mit `NOW()` geschrieben werden,
 *    wo der MySQL-Server in Server-lokaler TZ läuft.
 *
 * Alle Methoden akzeptieren `null`/leere Strings und geben dann einen
 * Fallback (`'–'` per Default) zurück — spart den
 * `$x ? date(…, strtotime($x)) : '–'`-Ternary in Templates.
 */
final class DateTimeHelper
{
    public const LOCAL_TZ = 'Europe/Berlin';

    // ── UTC-Input (neue Tabellen, z.B. Cron) ─────────────────────────

    /**
     * Parsed einen DB-Wert (z.B. "2026-04-24 10:58:22") als UTC und
     * konvertiert zu Europe/Berlin. Gibt null zurück bei leerem/ungültigem Input.
     */
    public static function toLocal(?string $utcString): ?\DateTimeImmutable
    {
        if ($utcString === null || $utcString === '') {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($utcString, new \DateTimeZone('UTC'));
            return $dt->setTimezone(new \DateTimeZone(self::LOCAL_TZ));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "24.04.2026 12:58" (UTC-Input). */
    public static function formatShort(?string $utcString, string $fallback = '–'): string
    {
        $local = self::toLocal($utcString);
        return $local === null ? $fallback : $local->format('d.m.Y H:i');
    }

    /** "24.04.2026 12:58:22" (UTC-Input). */
    public static function formatLong(?string $utcString, string $fallback = '–'): string
    {
        $local = self::toLocal($utcString);
        return $local === null ? $fallback : $local->format('d.m.Y H:i:s');
    }

    /** "12:58" (UTC-Input). */
    public static function formatTime(?string $utcString, string $fallback = '–'): string
    {
        $local = self::toLocal($utcString);
        return $local === null ? $fallback : $local->format('H:i');
    }

    /** "24.04.2026" (UTC-Input). */
    public static function formatDate(?string $utcString, string $fallback = '–'): string
    {
        $local = self::toLocal($utcString);
        return $local === null ? $fallback : $local->format('d.m.Y');
    }

    /**
     * "vor 3 min", "vor 2 std", "in 14 min" — relative Distanz zu jetzt.
     * Praktisch für Last-Run-Anzeigen in Listen.
     */
    public static function relative(?string $utcString, string $fallback = '–'): string
    {
        $local = self::toLocal($utcString);
        if ($local === null) {
            return $fallback;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::LOCAL_TZ));
        $diff = $now->getTimestamp() - $local->getTimestamp();
        $abs  = abs($diff);
        $future = $diff < 0;

        $text = match (true) {
            $abs < 60          => 'jetzt',
            $abs < 3600        => floor($abs / 60) . ' min',
            $abs < 86_400      => floor($abs / 3600) . ' std',
            $abs < 604_800     => floor($abs / 86_400) . ' tg',
            default            => floor($abs / 604_800) . ' wo',
        };
        if ($text === 'jetzt') return $text;
        return $future ? 'in ' . $text : 'vor ' . $text;
    }

    // ── Local-Input (historische Tabellen mit NOW()) ─────────────────

    /**
     * Parsed einen DB-Wert ohne TZ-Konvertierung — nutzt die PHP-Default-TZ.
     * Semantisch identisch zu `strtotime($x)` + `date()`.
     */
    public static function parseLocal(?string $localString): ?\DateTimeImmutable
    {
        if ($localString === null || $localString === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($localString);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "24.04.2026 12:58" (Local-Input). */
    public static function formatShortLocal(?string $localString, string $fallback = '–'): string
    {
        $dt = self::parseLocal($localString);
        return $dt === null ? $fallback : $dt->format('d.m.Y H:i');
    }

    /** "24.04.2026 12:58:22" (Local-Input). */
    public static function formatLongLocal(?string $localString, string $fallback = '–'): string
    {
        $dt = self::parseLocal($localString);
        return $dt === null ? $fallback : $dt->format('d.m.Y H:i:s');
    }

    /** "12:58" (Local-Input). */
    public static function formatTimeLocal(?string $localString, string $fallback = '–'): string
    {
        $dt = self::parseLocal($localString);
        return $dt === null ? $fallback : $dt->format('H:i');
    }

    /** "24.04.2026" (Local-Input). */
    public static function formatDateLocal(?string $localString, string $fallback = '–'): string
    {
        $dt = self::parseLocal($localString);
        return $dt === null ? $fallback : $dt->format('d.m.Y');
    }
}
