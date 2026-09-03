<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Auth\Permissions;
use App\Logging\Logger;
use App\Plugins\PluginLoader;
use App\Session\SessionManager;

/**
 * Die Navigation, wie der angemeldete Nutzer sie sieht.
 *
 * Liest config/navigation.php, hängt die Fragmente der aktiven Plugins an
 * (PluginLoader::mergeNavigation), lässt nur Gruppen und Einträge durch,
 * für die Permissions::check() zutrifft, und markiert den Eintrag der
 * aktuellen Seite. Sidebar (navbar-sidebar.php) und Topbar (topbar.php)
 * lesen beide von hier: die Sidebar zeichnet die Gruppen, die Topbar baut
 * aus den Schnellaktionen das Neu-Menü und aus allen Einträgen die Ziele
 * der Suche.
 */
final class Navigation
{
    /**
     * Sichtbare Gruppen mit sichtbaren Einträgen. Jeder Eintrag trägt
     * `icon` (mit Fallback) und `active` (bool); genau ein Eintrag ist
     * aktiv, der mit dem längsten passenden Pfad.
     *
     * @return list<array<string, mixed>>
     */
    public static function groups(): array
    {
        // Ohne Konto keine Navigation: die Kachelseite (dashboard.php) ist
        // auch ohne Anmeldung erreichbar und zeigt dann nur die Leiste.
        if (SessionManager::userId() === null) {
            return [];
        }

        $config = require dirname(__DIR__, 2) . '/config/navigation.php';
        if (!is_array($config)) {
            $config = [];
        }

        try {
            $config = app(PluginLoader::class)->mergeNavigation($config);
        } catch (\Throwable $e) {
            Logger::warning('Plugin-Navigation nicht geladen: ' . $e->getMessage());
        }

        $groups = [];
        foreach ((array) ($config['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            if (!empty($group['permissions']) && !Permissions::check($group['permissions'])) {
                continue;
            }

            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item) || !isset($item['label'], $item['href'])) {
                    continue;
                }
                if (!empty($item['permissions']) && !Permissions::check($item['permissions'])) {
                    continue;
                }
                if (!is_string($item['icon'] ?? null) || $item['icon'] === '') {
                    $item['icon'] = 'fa-solid fa-circle-dot';
                }
                $item['active'] = false;
                $items[] = $item;
            }
            if ($items === []) {
                continue;
            }

            $group['items'] = $items;
            $groups[] = $group;
        }

        self::markActive($groups, self::currentPath());

        return $groups;
    }

    /**
     * Die Schnellaktionen der sichtbaren Einträge, in Sidebar-Reihenfolge.
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array{type: string, target: string, parent: string, label: string, icon: string}>
     */
    public static function quickActions(array $groups): array
    {
        $actions = [];
        foreach ($groups as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $action = $item['quick_action'] ?? null;
                if (!is_array($action) || !isset($action['type'], $action['target'], $action['label'])) {
                    continue;
                }
                $actions[] = [
                    'type'   => (string) $action['type'],
                    'target' => (string) $action['target'],
                    'parent' => (string) $item['href'],
                    'label'  => (string) $action['label'],
                    'icon'   => is_string($action['icon'] ?? null) && $action['icon'] !== '' ? $action['icon'] : (string) $item['icon'],
                ];
            }
        }

        return $actions;
    }

    /**
     * Pfad der aktuellen Anfrage ohne BASE_PATH, ohne `.php`, ohne Schrägstrich
     * am Ende (die Wurzel bleibt `/`).
     */
    public static function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return self::normalize(is_string($path) ? $path : '/');
    }

    /**
     * Ein href als Pfad zum Vergleich mit currentPath(); null für externe
     * Ziele (andere Hosts), die nie aktiv sind.
     */
    public static function relativePath(string $href): ?string
    {
        if (preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $href) === 1) {
            $host = parse_url($href, PHP_URL_HOST);
            if (!is_string($host) || strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
                return null;
            }
            $href = parse_url($href, PHP_URL_PATH);
            if (!is_string($href)) {
                return null;
            }
        }

        $path = parse_url($href, PHP_URL_PATH);

        return self::normalize(is_string($path) ? $path : '/');
    }

    private static function normalize(string $path): string
    {
        // Der Name der Konstante steht in einer Variablen: BASE_PATH kommt zur
        // Laufzeit aus der Datenbank, PHPStan kennt nur den Fallback '/' aus
        // config.php und hielte den Vergleich unten sonst für immer falsch.
        $constant = 'BASE_PATH';
        $base = rtrim(defined($constant) ? (string) constant($constant) : '/', '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $path = preg_replace('~\.php$~i', '', $path) ?? $path;
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Markiert genau einen Eintrag als aktiv: den, dessen href oder eines
     * seiner `match`-Präfixe am längsten mit dem aktuellen Pfad
     * übereinstimmt. „Benutzer" (match /users/list, /users/edit) verliert
     * so gegen „Registrierungscodes" (href /users/registration-codes).
     *
     * @param list<array<string, mixed>> $groups
     */
    private static function markActive(array &$groups, string $current): void
    {
        $bestLength = -1;
        $bestGroup = null;
        $bestItem = null;

        foreach ($groups as $g => $group) {
            foreach ((array) $group['items'] as $i => $item) {
                $candidates = [];
                $href = self::relativePath((string) $item['href']);
                if ($href !== null) {
                    $candidates[] = $href;
                }
                foreach ((array) ($item['match'] ?? []) as $prefix) {
                    if (is_string($prefix) && $prefix !== '') {
                        $candidates[] = self::normalize($prefix);
                    }
                }
                foreach ($candidates as $candidate) {
                    $hit = $current === $candidate
                        || ($candidate !== '/' && str_starts_with($current, $candidate . '/'));
                    if ($hit && strlen($candidate) > $bestLength) {
                        $bestLength = strlen($candidate);
                        $bestGroup = $g;
                        $bestItem = $i;
                    }
                }
            }
        }

        if ($bestGroup !== null && $bestItem !== null) {
            $groups[$bestGroup]['items'][$bestItem]['active'] = true;
        }
    }
}
