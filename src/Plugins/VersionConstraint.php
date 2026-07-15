<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Minimaler Semver-Constraint-Matcher für Plugin-Manifeste.
 *
 * Es gibt bewusst keine composer/semver-Abhängigkeit — der Bedarf ist
 * klein: ein Plugin sagt `requires: ['ignis' => '>=1.2 <2.0']`, und der
 * PluginRegistry muss prüfen, ob die laufende ignis-Version dazu passt.
 *
 * Unterstützt werden mit Leerzeichen verknüpfte UND-Bedingungen, jeweils
 * mit einem Operator:
 *
 *   >=1.2 <2.0     (mind. 1.2, aber kleiner als 2.0)
 *   ^1.2           (>=1.2, <2.0 — gleiche Major)
 *   ~1.2.3         (>=1.2.3, <1.3.0 — gleiche Minor)
 *   =1.0.0         (exakt)
 *   *              (beliebig)
 *
 * Versionsvergleich läuft über PHP's version_compare(), führende „v" in
 * Version wie Constraint werden abgeschnitten.
 */
final class VersionConstraint
{
    /**
     * Erfüllt $version alle Bedingungen aus $constraint?
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $version = self::normalize($version);
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        // Mit Leerzeichen getrennte Teilbedingungen sind UND-verknüpft.
        foreach (preg_split('/\s+/', $constraint) ?: [] as $part) {
            if ($part === '' || !self::satisfiesPart($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesPart(string $version, string $part): bool
    {
        // Caret: ^1.2.3 → >=1.2.3 <2.0.0 (nächste Major)
        if (str_starts_with($part, '^')) {
            $base = self::normalize(substr($part, 1));
            $upper = (self::segment($base, 0) + 1) . '.0.0';
            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }

        // Tilde: ~1.2.3 → >=1.2.3 <1.3.0 (nächste Minor)
        if (str_starts_with($part, '~')) {
            $base = self::normalize(substr($part, 1));
            $upper = self::segment($base, 0) . '.' . (self::segment($base, 1) + 1) . '.0';
            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }

        foreach (['>=', '<=', '==', '!=', '=', '>', '<'] as $op) {
            if (str_starts_with($part, $op)) {
                $target = self::normalize(substr($part, strlen($op)));
                $cmpOp = $op === '=' ? '==' : $op;
                return version_compare($version, $target, $cmpOp);
            }
        }

        // Kein Operator → exakter Match.
        return version_compare($version, self::normalize($part), '==');
    }

    private static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    /** Numerischer Wert des n-ten Punkt-Segments (fehlend = 0). */
    private static function segment(string $version, int $index): int
    {
        $parts = explode('.', $version);
        return isset($parts[$index]) ? (int) $parts[$index] : 0;
    }
}
