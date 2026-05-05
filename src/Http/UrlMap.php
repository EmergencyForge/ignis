<?php

declare(strict_types=1);

namespace App\Http;

/**
 * UrlMap — Source-of-Truth fuer die deutsch->englisch URL-Migration.
 *
 * intraRP ist als ıgnıs auf englische Pfade umgezogen. Alte deutsche URLs
 * (`/kalender`, `/manv`, `/benutzer`, ...) leiten weiterhin via 301/308 auf
 * die neuen englischen Pfade um — diese Klasse ist die zentrale Mapping-
 * Tabelle, die sowohl der Front-Controller-Redirector (public/index.php)
 * als auch die Auto-Translation in `Controller::redirect()` nutzen.
 *
 * Drei Kategorien von Mapping:
 *
 *   1. **Top-Segment** — `/kalender/X` → `/calendar/X`
 *   2. **API-Segment** — `/api/kalender/X` → `/api/calendar/X` (auch /api/v1/...)
 *   3. **Settings-Segment** — `/settings/<de>/.../...` → `/settings/<en>/.../...`
 *
 * Trailing-Slash bleibt **strikt** erhalten — `/kalender/` → `/calendar/`,
 * `/kalender` → `/calendar`. Sub-Pfade und Query-Strings werden 1:1 durch-
 * gereicht (Query-String wird vom Caller separat angefuegt).
 */
final class UrlMap
{
    /**
     * Top-Level-Segment-Mapping. Das erste Pfad-Segment wird gegen diese
     * Tabelle geprueft.
     *
     * @var array<string,string>
     */
    public const LEGACY_TO_CANONICAL = [
        'benutzer'           => 'users',
        'antrag'             => 'forms',
        'antraege'           => 'forms',
        'benachrichtigungen' => 'notifications',
        'fahrtenbuch'        => 'logbook',
        'kalender'           => 'calendar',
        'mitarbeiter'        => 'personnel',
        'manv'               => 'mci',
        'einsatz'            => 'firetab',
        'wissensdb'          => 'lexicon',
        'dokumente'          => 'documents',
    ];

    /**
     * Settings-Sub-Segment-Mapping. Greift bei Pfaden der Form
     * `/settings/<key>/...` und uebersetzt `<key>` einzeln. Beispiel:
     * `/settings/vehicles/defects/index` → `/settings/vehicles/defects/index`
     * (zwei Lookups, weil `fahrzeuge` und `defekte` beide hier drin sind).
     *
     * @var array<string,string>
     */
    public const SETTINGS_LEGACY_TO_CANONICAL = [
        'fahrzeuge'    => 'vehicles',
        'dienstgrade'  => 'ranks',
        'qualifw'      => 'fdskills',
        'qualird'      => 'ambskills',
        'qualifd'      => 'specialties',
        'beladelisten' => 'vehload',
        'defekte'      => 'defects',
        'medikamente'  => 'medications',
        'antrag'       => 'forms',
        'personal'     => 'personnel',
    ];

    /**
     * API-Sub-Segment-Mapping. Greift bei `/api/<key>/...` und
     * `/api/v1/<key>/...` — beide Formen werden vom Front-Controller
     * geroutet, der Redirector deckt also beide ab.
     *
     * @var array<string,string>
     */
    public const API_LEGACY_TO_CANONICAL = [
        'kalender'    => 'calendar',
        'manv'        => 'mci',
        'mitarbeiter' => 'personnel',
    ];

    /**
     * Uebersetzt einen Pfad in seine kanonische Form.
     *
     * @return string|null  Kanonischer Pfad (mit fuehrendem `/`),
     *                      oder null wenn keine Uebersetzung noetig
     *                      (Pfad ist bereits englisch oder nicht im Mapping).
     */
    public static function translatePath(string $path): ?string
    {
        if ($path === '' || $path === '/') {
            return null;
        }

        // Trailing-Slash merken, damit am Ende rekonstruiert
        $hasTrailingSlash = str_ends_with($path, '/');
        $trimmed = $hasTrailingSlash ? rtrim($path, '/') : $path;

        // Pfad in Segmente zerlegen (ohne fuehrenden Leer-Eintrag)
        $segments = array_values(array_filter(explode('/', $trimmed), static fn ($s) => $s !== ''));
        if ($segments === []) {
            return null;
        }

        $changed = false;

        // Settings-Block: /settings/<sub>/... — sub gegen Settings-Map mappen
        if ($segments[0] === 'settings' && isset($segments[1])) {
            // Iteriere Segmente ab Index 1 und mappe alles, was im Settings-Map steht.
            // Das deckt /settings/vehicles/defects/index ebenso wie
            // /settings/personnel/fdskills/index ab.
            for ($i = 1; $i < count($segments); $i++) {
                if (isset(self::SETTINGS_LEGACY_TO_CANONICAL[$segments[$i]])) {
                    $segments[$i] = self::SETTINGS_LEGACY_TO_CANONICAL[$segments[$i]];
                    $changed = true;
                }
            }
        // API-Block: /api/<sub>/... oder /api/v1/<sub>/...
        } elseif ($segments[0] === 'api' && isset($segments[1])) {
            // Skip optional v1-Prefix
            $apiKeyIdx = ($segments[1] === 'v1' && isset($segments[2])) ? 2 : 1;
            if (isset($segments[$apiKeyIdx], self::API_LEGACY_TO_CANONICAL[$segments[$apiKeyIdx]])) {
                $segments[$apiKeyIdx] = self::API_LEGACY_TO_CANONICAL[$segments[$apiKeyIdx]];
                $changed = true;
            }
        // Top-Level-Block: /<key>/...
        } elseif (isset(self::LEGACY_TO_CANONICAL[$segments[0]])) {
            $segments[0] = self::LEGACY_TO_CANONICAL[$segments[0]];
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $result = '/' . implode('/', $segments);
        if ($hasTrailingSlash) {
            $result .= '/';
        }
        return $result;
    }

    /**
     * Variante fuer Controller::redirect(): bekommt einen relativen Pfad
     * ohne fuehrenden `/` (z.B. `'kalender/view?id=42'`) und gibt die
     * uebersetzte Form zurueck (`'calendar/view?id=42'`). Query-String
     * bleibt erhalten.
     */
    public static function translateRelative(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        // Query-String separat halten — wir uebersetzen nur den Pfad-Anteil.
        $queryPos = strpos($relativePath, '?');
        $pathPart = $queryPos === false ? $relativePath : substr($relativePath, 0, $queryPos);
        $queryPart = $queryPos === false ? '' : substr($relativePath, $queryPos);

        // translatePath() arbeitet mit fuehrendem `/`, also kurz aufsetzen + entfernen
        $translated = self::translatePath('/' . ltrim($pathPart, '/'));
        if ($translated === null) {
            return null;
        }
        return ltrim($translated, '/') . $queryPart;
    }
}
