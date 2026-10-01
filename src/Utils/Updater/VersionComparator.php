<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Regeln für Versionsnummern: Format, Reihenfolge, Vorabversionen und wie
 * dringend ein Update ist. Gilt für das Jahresschema (v2026.0.8) genauso wie
 * für die alten 1.x-Nummern.
 */
final class VersionComparator
{
    /**
     * Erlaubt: bis zu fünf Zahlenstellen mit optionalem Vorabsuffix
     * (v2026.1.0-beta.1) und Branch-Builds (dev-main-abc12345).
     */
    public static function isValidFormat(string $version): bool
    {
        return preg_match('/^v?\d+(\.\d+){0,4}(-[a-zA-Z0-9.-]+)?$/', $version) === 1
            || preg_match('/^dev-[a-zA-Z0-9._\/-]+-[a-f0-9]{7,8}$/', $version) === 1;
    }

    /** true, wenn $candidate neuer ist als $installed */
    public static function isNewer(string $candidate, string $installed): bool
    {
        return version_compare(ltrim($candidate, 'v'), ltrim($installed, 'v'), '>');
    }

    public static function isPreRelease(string $version): bool
    {
        return preg_match('/(alpha|beta|rc|dev)/i', $version) === 1;
    }

    /**
     * Neue Hauptversion: high. Neue Minor: medium ab 60 Tagen Alter, sonst
     * low. Neuer Patch: medium ab 30 Tagen, sonst low.
     *
     * @return 'high'|'medium'|'low'
     */
    public static function urgency(string $installed, string $latest, int $ageDays): string
    {
        $currentParts = explode('.', ltrim($installed, 'v'));
        $latestParts = explode('.', ltrim($latest, 'v'));

        if ($latestParts[0] > $currentParts[0]) {
            return 'high';
        }

        if (($latestParts[1] ?? 0) > ($currentParts[1] ?? 0)) {
            return $ageDays > 60 ? 'medium' : 'low';
        }

        if (($latestParts[2] ?? 0) > ($currentParts[2] ?? 0)) {
            return $ageDays > 30 ? 'medium' : 'low';
        }

        return 'low';
    }
}
