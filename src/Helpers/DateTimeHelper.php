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

    /** Abstand der DB-Uhr zu UTC in Sekunden, je Request einmal erfragt. */
    private static ?int $dbUtcOffset = null;

    // ── DB-Uhr-Input (Felder mit DEFAULT CURRENT_TIMESTAMP oder NOW()) ──

    /**
     * Abstand der Datenbankuhr zu UTC in Sekunden. Die Verbindung setzt keine
     * Zeitzone, NOW() und CURRENT_TIMESTAMP laufen also in der Zeitzone des
     * DB-Servers: im Docker-Betrieb UTC, auf manchen Hostern Ortszeit. Ohne
     * Verbindung gilt die Ortszeit, wie bisher bei den Local-Methoden.
     */
    public static function dbUtcOffset(): int
    {
        if (self::$dbUtcOffset === null) {
            try {
                $row = \Illuminate\Database\Capsule\Manager::connection()
                    ->selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS diff');
                $seconds = is_object($row) && isset($row->diff) ? (int) $row->diff : 0;
                // Beide Uhren liest MySQL im selben Statement, auf Viertelstunden gerundet bleibt nur der Zonenabstand.
                self::$dbUtcOffset = (int) (round($seconds / 900) * 900);
            } catch (\Throwable) {
                self::$dbUtcOffset = (new \DateTimeImmutable('now', new \DateTimeZone(self::LOCAL_TZ)))->getOffset();
            }
        }

        return self::$dbUtcOffset;
    }

    /** Für Tests: festen Abstand setzen, null fragt die Datenbank wieder. */
    public static function useDbUtcOffset(?int $seconds): void
    {
        self::$dbUtcOffset = $seconds;
    }

    /**
     * Parsed einen Wert, den die Datenbankuhr geschrieben hat, und liefert ihn
     * in Europe/Berlin. Läuft der DB-Server in UTC, wird aus 22:30 so 00:30
     * des Folgetags; läuft er in Ortszeit, bleibt der Wert, wie er ist.
     */
    public static function fromDbClock(?string $dbString): ?\DateTimeImmutable
    {
        if ($dbString === null || $dbString === '') {
            return null;
        }
        try {
            $asUtc = new \DateTimeImmutable($dbString, new \DateTimeZone('UTC'));
            return $asUtc
                ->modify(sprintf('%+d seconds', -self::dbUtcOffset()))
                ->setTimezone(new \DateTimeZone(self::LOCAL_TZ));
        } catch (\Throwable) {
            return null;
        }
    }

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
