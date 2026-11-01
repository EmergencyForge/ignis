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
                // Die Schnellaktion darf ein eigenes Recht verlangen (Termin
                // anlegen braucht mehr als Kalender sehen); ohne das Recht
                // bleibt der Eintrag, das Plus verschwindet.
                $quick = $item['quick_action'] ?? null;
                if (is_array($quick) && !empty($quick['permissions']) && !Permissions::check($quick['permissions'])) {
                    unset($item['quick_action']);
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
     * Gruppen für die Sidebar: die `placement => 'settings'`-Gruppen aus
     * groups() bleiben dort ungerendert, stattdessen bekommt „Verwaltung"
     * (id `admin`) einen synthetischen Eintrag „Einstellungen" dazu, aber nur,
     * wenn nach der Rechteprüfung noch mindestens eine Settings-Gruppe
     * einen Eintrag hat. Aktiv ist er, wenn irgendein Settings-Eintrag es
     * ist (z.B. Rollen unter /users/roles) oder die Übersicht selbst
     * offen ist; das deckt automatisch jede Unterseite ab, ohne die Liste
     * ihrer Pfade hier zu pflegen.
     *
     * @param list<array<string, mixed>> $groups Ergebnis von groups()
     * @return list<array<string, mixed>>
     */
    public static function sidebarGroups(array $groups): array
    {
        $visible = [];
        $hasSettingsItem = false;
        $settingsActive = false;

        foreach ($groups as $group) {
            if (($group['placement'] ?? null) === 'settings') {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $hasSettingsItem = true;
                    if (!empty($item['active'])) {
                        $settingsActive = true;
                    }
                }
                continue;
            }
            $visible[] = $group;
        }

        if (!$hasSettingsItem) {
            return $visible;
        }

        $current = self::currentPath();
        $overviewOpen = $current === '/settings' || $current === '/settings/index';
        $einstellungen = [
            'label'  => 'Einstellungen',
            'href'   => (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'settings/index',
            'icon'   => 'fa-solid fa-gear',
            'active' => $settingsActive || $overviewOpen,
        ];

        $foundAdmin = false;
        foreach ($visible as &$group) {
            if (($group['id'] ?? null) === 'admin') {
                $group['items'][] = $einstellungen;
                $foundAdmin = true;
                break;
            }
        }
        unset($group);

        // „Verwaltung" selbst kann leer und damit aus groups() gefallen sein,
        // z.B. wenn Benutzer sehen fehlt, aber ein anderes Recht für einen
        // Settings-Bereich reicht; dann bekommt die Sidebar die Gruppe hier
        // wieder, nur mit dem Einstellungen-Eintrag.
        if (!$foundAdmin) {
            $visible[] = ['id' => 'admin', 'label' => 'Verwaltung', 'items' => [$einstellungen]];
        }

        return $visible;
    }

    /**
     * Gruppen der Sidebar im Einstellungsmodus: die `placement => 'settings'`-Gruppen
     * aus groups(), in derselben Reihenfolge. Aktiv ist hier der Eintrag,
     * der unter diesen Einträgen am besten zum Pfad passt, auch wenn in der
     * normalen Sidebar ein anderer gewinnt: /settings/vehicles/vehload/index
     * gehört dort zu „Fahrzeuge“, in den Einstellungen zu „Beladelisten“.
     *
     * @param list<array<string, mixed>> $groups Ergebnis von groups()
     * @return list<array<string, mixed>>
     */
    public static function settingsGroups(array $groups): array
    {
        $settings = [];
        foreach ($groups as $group) {
            if (($group['placement'] ?? null) !== 'settings' || ($group['items'] ?? []) === []) {
                continue;
            }
            foreach ($group['items'] as &$item) {
                $item['active'] = false;
            }
            unset($item);
            $settings[] = $group;
        }

        self::markActive($settings, self::currentPath());

        return $settings;
    }

    /**
     * Gehört die aktuelle Seite in die Einstellungen? Dann zeigt die Sidebar
     * deren Gruppen statt der normalen. Ja auf der Übersicht /settings und
     * überall, wo ein Eintrag der Leiste aktiv ist.
     *
     * @param list<array<string, mixed>> $settingsGroups Ergebnis von settingsGroups()
     */
    public static function inSettings(array $settingsGroups): bool
    {
        if ($settingsGroups === []) {
            return false;
        }
        if (in_array(self::currentPath(), ['/settings', '/settings/index'], true)) {
            return true;
        }
        foreach ($settingsGroups as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['active'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Die Felder eines Eintrags für die Suche (Strg K), je mit Beschriftung
     * und Link auf den Anker der Seite. `fields` ist eine Liste oder eine
     * Closure, die sie liefert (System-Konfiguration: aus intra_config).
     * Schlägt die Closure fehl, findet die Suche eben nur die Bereiche.
     *
     * @param array<string, mixed> $item
     * @return list<array{label: string, href: string}>
     */
    public static function settingsFields(array $item): array
    {
        $fields = $item['fields'] ?? [];
        if ($fields instanceof \Closure) {
            try {
                $fields = $fields();
            } catch (\Throwable $e) {
                Logger::warning('Felder der Einstellungen für die Suche nicht geladen: ' . $e->getMessage());
                $fields = [];
            }
        }

        $href = explode('#', (string) $item['href'])[0];
        $result = [];
        foreach (is_array($fields) ? $fields : [] as $field) {
            if (!is_array($field) || !is_string($field['label'] ?? null) || !is_string($field['anchor'] ?? null)) {
                continue;
            }
            if (trim($field['label']) === '' || $field['anchor'] === '') {
                continue;
            }
            $result[] = ['label' => trim($field['label']), 'href' => $href . '#' . rawurlencode($field['anchor'])];
        }

        return $result;
    }

    /**
     * Die Schnellaktionen der sichtbaren Einträge, in Sidebar-Reihenfolge.
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array{type: string, target: string, parent: string, label: string, icon: string, quick: ?string}>
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
                    'quick'  => is_string($action['quick'] ?? null) ? $action['quick'] : null,
                ];
            }
        }

        return $actions;
    }

    /**
     * Einträge der Palette ohne Server (assets/js/ui/palette.js): „X anlegen"
     * aus den Schnellaktionen und „Gehe zu" aus der sichtbaren Navigation.
     * Topbar und Desktop-Hülle hängen sie als JSON an das Suchfeld.
     *
     * @param list<array<string, mixed>> $groups Ergebnis von groups()
     * @return list<array{label: string, sub: string, href: string, drawer: bool, keywords: string, quick?: string}>
     */
    public static function palette(array $groups): array
    {
        $palette = [];
        foreach (self::quickActions($groups) as $action) {
            $palette[] = [
                'label'    => $action['label'],
                'sub'      => 'Neu',
                'href'     => $action['type'] === 'modal'
                    ? $action['parent'] . (str_contains($action['parent'], '?') ? '&' : '?') . 'action=create&quick=' . rawurlencode($action['target'])
                    : $action['target'],
                'drawer'   => $action['type'] === 'drawer',
                'keywords' => 'neu anlegen erstellen',
            ] + ($action['quick'] !== null ? ['quick' => $action['quick']] : []);
        }
        foreach ($groups as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $palette[] = [
                    'label'    => (string) $item['label'],
                    'sub'      => 'Gehe zu',
                    'href'     => (string) $item['href'],
                    'drawer'   => false,
                    'keywords' => 'gehe zu öffnen ' . (is_string($group['label'] ?? null) ? $group['label'] : '') . ' ' . (string) ($item['keywords'] ?? ''),
                ];
                // Einzelne Felder der Einstellungen, etwa „Hauptfarbe“: springen auf
                // ihr Feld (config/navigation.php, `fields`).
                foreach (self::settingsFields($item) as $field) {
                    $palette[] = [
                        'label'    => $field['label'],
                        'sub'      => (string) $item['label'],
                        'href'     => $field['href'],
                        'drawer'   => false,
                        'keywords' => 'einstellung feld ' . (string) $item['label'],
                    ];
                }
            }
        }

        return $palette;
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

    /**
     * BASE_PATH ohne Schrägstrich am Ende, also '' bei einer Installation in
     * der Wurzel. Eigene Methode, damit PHPStan einen string sieht: es kennt
     * nur den Fallback '/' aus config.php, der echte Wert kommt zur Laufzeit
     * aus der Datenbank, und der Vergleich in normalize() wäre sonst „immer
     * falsch".
     */
    private static function basePath(): string
    {
        return rtrim(defined('BASE_PATH') ? (string) BASE_PATH : '/', '/');
    }

    private static function normalize(string $path): string
    {
        $base = self::basePath();
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
     * so gegen „Einladungen" (href /users/registration-codes).
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
