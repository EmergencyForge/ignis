<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\Navigation;
use App\Helpers\Theme;
use App\Session\SessionManager;
use EmergencyForge\Ui\Desk;
use EmergencyForge\Ui\Ui;

/**
 * Die Desktop-Hülle (efOS) für ignis: welche Oberfläche dieses Gerät will
 * und die Konfiguration für EmergencyForge\Ui\Desk aus der Navigation.
 *
 * Die Oberfläche hängt am Gerät, nicht am Konto: am PC Desktop, im Spiel
 * Tablet. Sie steht im Cookie `ignis_ui` (classic, auto, desktop, tablet),
 * ohne Cookie gilt die klassische Hülle. ef_bridge hängt beim Öffnen
 * `?ui=tablet` an, das setzt das Cookie.
 *
 * eNOTF und fireTab bleiben eigene Tablets: im Dock stehen sie, öffnen
 * aber ihre eigene Seite statt eines Fensters.
 */
final class DeskShell
{
    public const COOKIE = 'ignis_ui';

    /**
     * Apps nach Pfad (ohne BASE_PATH): Ton des Symbols, eigene ID, unter
     * welchen Pfaden eine Seite zur App gehört, und Sonderfälle.
     *
     * `label` ersetzt die Beschriftung der Sidebar, wo sie als Verb gedacht
     * ist („eNOTF öffnen“) und unter dem Symbol nur stört.
     *
     * @var array<string, array{id: string, tone: string, label?: string, prefixes?: list<string>, dock?: bool, single?: bool, external?: bool, size?: array{int, int}}>
     */
    private const APPS = [
        'personnel/list'                   => ['id' => 'personnel', 'tone' => 'indigo', 'prefixes' => ['/personnel'], 'dock' => true],
        'calendar'                         => ['id' => 'calendar', 'tone' => 'pink', 'dock' => true],
        'mail'                             => ['id' => 'mail', 'tone' => 'blue', 'single' => true, 'dock' => true],
        'lexicon/index'                    => ['id' => 'lexicon', 'tone' => 'teal', 'prefixes' => ['/lexicon']],
        'forms/admin/list'                 => ['id' => 'forms', 'tone' => 'amber', 'prefixes' => ['/forms']],
        'enotf'                            => ['id' => 'enotf', 'tone' => 'red', 'label' => 'eNOTF', 'external' => true, 'dock' => true],
        'enotf/admin/list'                 => ['id' => 'enotf-admin', 'tone' => 'red', 'prefixes' => ['/enotf/admin']],
        'firetab'                          => ['id' => 'firetab', 'tone' => 'orange', 'label' => 'fireTab', 'external' => true, 'dock' => true],
        'firetab/admin/list'               => ['id' => 'firetab-qm', 'tone' => 'orange', 'prefixes' => ['/firetab/admin']],
        'mci'                              => ['id' => 'mci', 'tone' => 'purple'],
        'settings/vehicles/vehicles/index' => ['id' => 'vehicles', 'tone' => 'cyan', 'prefixes' => ['/settings/vehicles/vehicles']],
        'settings/vehicles/defects/index'  => ['id' => 'defects', 'tone' => 'amber', 'prefixes' => ['/settings/vehicles/defects']],
        'logbook/index'                    => ['id' => 'logbook', 'tone' => 'green', 'prefixes' => ['/logbook']],
        'users/list'                       => ['id' => 'users', 'tone' => 'slate', 'prefixes' => ['/users/list', '/users/edit', '/users/create']],
        'settings/index'                   => ['id' => 'settings', 'tone' => 'graphite', 'prefixes' => ['/settings', '/users/roles', '/users/registration-codes', '/users/audit-log']],
    ];

    public static function mode(): string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;

        return Desk::mode(is_string($cookie) ? $cookie : null);
    }

    public static function active(): bool
    {
        return self::mode() !== 'classic' && SessionManager::userId() !== null;
    }

    /**
     * Übernimmt `?ui=` aus der Adresse ins Cookie. Gibt true zurück, wenn
     * sich etwas geändert hat.
     */
    public static function rememberFromQuery(): bool
    {
        $wanted = $_GET['ui'] ?? null;
        if (!is_string($wanted) || !in_array($wanted, Desk::MODES, true)) {
            return false;
        }
        self::remember($wanted);

        return true;
    }

    public static function remember(string $mode): void
    {
        $mode = Desk::mode($mode);
        $_COOKIE[self::COOKIE] = $mode;
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $mode, [
            'expires' => time() + 31536000,
            'path' => self::basePath(),
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
            'httponly' => false,
        ]);
    }

    /**
     * Die Gruppen der Sidebar als Apps. Einträge ohne Ziel und das
     * Dashboard (der Schreibtisch selbst) werden keine App.
     *
     * @param list<array<string, mixed>> $groups Ergebnis von Navigation::sidebarGroups()
     * @return list<array{label: string|null, items: list<array<string, mixed>>}>
     */
    public static function nav(array $groups): array
    {
        $base = self::basePath();
        $nav = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item) || !is_string($item['href'] ?? null)) {
                    continue;
                }
                $path = self::localPath((string) $item['href'], $base);
                if ($path === 'index' || $path === '') {
                    continue;
                }
                $spec = self::APPS[$path] ?? [];
                if (!empty($item['external']) && $spec === []) {
                    $spec = ['external' => true];
                }
                if (isset($spec['label'])) {
                    $item['label'] = $spec['label'];
                }
                unset($spec['label']);
                $item['app'] = $spec;
                $item['count'] = isset($item['counter']) && is_string($item['counter']) ? NavigationCounters::for($item['counter']) : null;
                $items[] = $item;
            }
            if ($items !== []) {
                $nav[] = ['label' => is_string($group['label'] ?? null) ? $group['label'] : null, 'items' => $items];
            }
        }

        return $nav;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $base = self::basePath();
        $groups = Navigation::groups();
        $sidebar = Navigation::sidebarGroups($groups);
        $name = (string) ($_SESSION['cirs_username'] ?? '');
        $userId = SessionManager::userId();

        return (new Desk(self::ui()))->config(self::nav($sidebar), [
            'product' => [
                'name' => defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : 'ignis',
                'mark' => self::mark(),
                'home' => $base . 'index',
                'version' => self::version(),
            ],
            'basePath' => rtrim($base, '/'),
            'storageKey' => 'ignis.desk.' . ($userId !== null ? (string) $userId : 'local'),
            'mode' => self::mode(),
            'cookie' => self::COOKIE,
            'menu' => [
                'settings' => self::hasSettings($sidebar) ? $base . 'settings/index' : null,
                'logout' => $base . 'logout',
                'inbox' => $base . 'inbox',
                'inboxCount' => (int) (NavigationCounters::for('inbox') ?? 0),
                'notificationsUrl' => $base . 'inbox/notifications',
                'inboxPopover' => $base . 'inbox/popover',
            ],
            'create' => self::createByApp($groups, $base),
            'user' => ['initials' => mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 2)), 'name' => $name],
            'theme' => [
                'action' => $base . 'profile/theme',
                'field' => 'csrf_token',
                'token' => csrf_token(),
                'current' => Theme::mode(),
            ],
            'now' => (int) round(microtime(true) * 1000),
        ]);
    }

    public static function render(string $initialUrl, string $initialHtml): string
    {
        return (new Desk(self::ui()))->render(self::config(), $initialUrl, $initialHtml);
    }

    public static function ui(): Ui
    {
        return new Ui('ignis');
    }

    /**
     * Die Schnellaktionen der Navigation nach Apps: jede hängt an der App
     * des Eintrags, an dem sie in der Sidebar steht.
     *
     * @param list<array<string, mixed>> $groups
     * @return array<string, list<array{label: string, href: string, drawer: bool}>>
     */
    private static function createByApp(array $groups, string $base): array
    {
        $map = [];
        foreach (Navigation::palette($groups) as $entry) {
            if ($entry['sub'] !== 'Neu') {
                continue;
            }
            $path = self::localPath($entry['href'], $base);
            foreach (self::APPS as $appPath => $spec) {
                foreach ($spec['prefixes'] ?? ['/' . $appPath] as $prefix) {
                    if ($path === ltrim($prefix, '/') || str_starts_with($path, ltrim($prefix, '/') . '/') || str_starts_with($path, ltrim($prefix, '/') . '?')) {
                        $map[$spec['id']][] = ['label' => $entry['label'], 'href' => $entry['href'], 'drawer' => $entry['drawer']];
                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    /** @param list<array<string, mixed>> $sidebar */
    private static function hasSettings(array $sidebar): bool
    {
        foreach ($sidebar as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (is_array($item) && self::localPath((string) ($item['href'] ?? ''), self::basePath()) === 'settings/index') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function localPath(string $href, string $base): string
    {
        if (parse_url($href, PHP_URL_HOST) !== null) {
            return $href;
        }
        $path = (string) parse_url($href, PHP_URL_PATH);
        if ($base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $query = parse_url($href, PHP_URL_QUERY);

        return trim($path, '/') . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    // Das Zeichen inline, damit es per currentColor die Farbe der Leiste
    // annimmt; über <img> käme keine Farbe an.
    private static function mark(): string
    {
        $svg = @file_get_contents(dirname(__DIR__, 2) . '/assets/img/ignis-mark.svg');

        return is_string($svg) ? str_replace(' role="img" aria-label="ignis"', ' aria-hidden="true"', trim($svg)) : '';
    }

    private static function version(): string
    {
        $file = dirname(__DIR__, 2) . '/storage/version.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : '';
    }

    private static function basePath(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';

        return $base === '' ? '/' : $base;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
