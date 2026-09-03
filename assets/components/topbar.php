<?php

declare(strict_types=1);

/**
 * assets/components/topbar.php — Leiste über allen eingeloggten Ansichten.
 *
 * Links der Sidebar-Schalter und die Wortmarke (SYSTEM_LOGO, sonst die
 * ignis-Wortmarke in Textfarbe), in der Mitte das Suchfeld (Ctrl+K springt
 * hinein; die Palette in global-search.js öffnet sich darüber), rechts das
 * Neu-Menü mit den Schnellaktionen der Navigation, die der Betrachter
 * sehen darf, und das Kontomenü mit Darstellungswechsel, Benachrichtigungen
 * und Abmelden. Die Menüs sind <details>, damit sie ohne JS funktionieren;
 * shell.js schließt sie bei Klick daneben. Eine Glocke kommt mit I9.
 *
 * Läuft per `require` im Scope des Layouts (oder des Shims navbar.php);
 * lokale Variablen tragen das Präfix `top` (SidebarScopeTest).
 */

use App\Helpers\Navigation;
use App\Helpers\Theme;
use App\Notifications\NotificationManager;
use App\Security\CsrfProtection;

$topBasePath = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$topLoggedIn = isset($_SESSION['userid']);
$topUsername = (string) ($_SESSION['cirs_username'] ?? 'Unbekannt');
$topInitials = mb_strtoupper(mb_substr($topUsername, 0, 2));

// Rolle: Name aus der Session; eine Session von vor setRoleDetails() hat
// keinen und bekommt ihn aus den Permissions abgeleitet. Die Farbe kommt
// als Bootstrap-Name und wird auf die Tokens abgebildet.
$topRoleName = $_SESSION['role_name'] ?? null;
if (!is_string($topRoleName) || $topRoleName === '') {
    $topPerms = $_SESSION['permissions'] ?? [];
    $topRoleName = is_array($topPerms) && in_array('full_admin', $topPerms, true) ? 'Admin+' : 'Benutzer';
}
$topRoleColors = [
    'primary'   => 'var(--accent)',
    'secondary' => 'var(--text-3)',
    'success'   => 'var(--ok)',
    'danger'    => 'var(--danger)',
    'warning'   => 'var(--warn)',
    'info'      => 'var(--info)',
    'light'     => 'var(--text-2)',
    'dark'      => 'var(--text-3)',
];
$topRoleColor = $topRoleColors[$_SESSION['role_color'] ?? 'secondary'] ?? 'var(--text-3)';

$topTheme  = Theme::mode();
$topThemes = [
    'dark'   => ['Dunkel', 'moon'],
    'light'  => ['Hell', 'sun'],
    'system' => ['Wie das System', 'circle-half-stroke'],
];

$topUnread = 0;
if ($topLoggedIn) {
    try {
        $topUnread = (int) (new NotificationManager())->getUnreadCount((int) $_SESSION['userid']);
    } catch (\Throwable $topError) {
        $topUnread = 0;
    }
}

// Logo: SYSTEM_LOGO, wenn der Betreiber eines hinterlegt hat; sonst die
// Wortmarke als Text in currentColor, damit sie die Textfarbe des
// Themes übernimmt (assets/img/ignis-wordmark.svg hat eine feste Farbe).
$topLogo = defined('SYSTEM_LOGO') ? trim((string) SYSTEM_LOGO) : '';
$topLogoIsDefault = $topLogo === ''
    || str_ends_with($topLogo, '/ignis-wordmark.svg')
    || str_ends_with($topLogo, '/defaultLogo.webp')
    || str_ends_with($topLogo, '/defaultLogo.png');
if (!$topLogoIsDefault && !preg_match('~^(https?:)?//~i', $topLogo)) {
    $topLogo = rtrim($topBasePath, '/') . '/' . ltrim($topLogo, '/');
}

$topGroups  = $topLoggedIn ? Navigation::groups() : [];
$topActions = Navigation::quickActions($topGroups);

// Einträge der Palette (global-search.js): alle sichtbaren Ziele und die
// Schnellaktionen, dazu die Treffer aus GET /api/system/global-search.
$topCommands = [];
foreach ($topGroups as $topGroup) {
    foreach ($topGroup['items'] as $topItem) {
        $topCommands[] = [
            'group'    => 'Navigation',
            'icon'     => (string) $topItem['icon'],
            'title'    => (string) $topItem['label'],
            'subtitle' => is_string($topGroup['label'] ?? null) ? $topGroup['label'] : 'Öffnen',
            'url'      => (string) $topItem['href'],
            'keywords' => 'gehe zu öffnen',
        ];
    }
}
foreach ($topActions as $topAction) {
    $topCommands[] = [
        'group'    => 'Schnellaktionen',
        'icon'     => $topAction['icon'],
        'title'    => $topAction['label'],
        'subtitle' => 'Neu',
        'url'      => $topAction['type'] === 'link'
            ? $topAction['target']
            : $topAction['parent'] . (str_contains($topAction['parent'], '?') ? '&' : '?') . 'action=create&quick=' . rawurlencode($topAction['target']),
        'keywords' => 'neu anlegen erstellen',
    ];
}
?>
<header class="ignis-topbar" data-base-path="<?= htmlspecialchars($topBasePath, ENT_QUOTES) ?>">
    <button type="button" class="ignis-topbar__toggle" data-ignis-sidebar-toggle aria-label="Navigation ein- oder ausklappen" title="Navigation ein- oder ausklappen ([)">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a href="<?= htmlspecialchars($topBasePath . 'index', ENT_QUOTES) ?>" class="ignis-topbar__mark" aria-label="<?= htmlspecialchars((string) SYSTEM_NAME, ENT_QUOTES) ?>">
        <?php if ($topLogoIsDefault): ?>
            <svg viewBox="0 0 160 56" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true" focusable="false">
                <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="Geist, system-ui, sans-serif" font-weight="800" font-style="italic" font-size="42" letter-spacing="-0.02em" fill="currentColor">ıgnıs</text>
            </svg>
        <?php else: ?>
            <img src="<?= htmlspecialchars($topLogo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) SYSTEM_NAME, ENT_QUOTES) ?>">
        <?php endif; ?>
    </a>

    <?php if ($topLoggedIn): ?>
        <div class="ignis-topbar__search" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="ignis-topbar__search-input" data-ignis-global-search placeholder="Suchen oder Schnellaktion starten" aria-label="Suche" autocomplete="off">
            <kbd class="ignis-topbar__kbd" aria-hidden="true">Ctrl K</kbd>
        </div>
    <?php endif; ?>

    <div class="ignis-topbar__spacer"></div>

    <?php if ($topActions !== []): ?>
        <details class="ignis-menu ignis-menu--right" data-ignis-menu>
            <summary class="ignis-btn ignis-btn--primary ignis-btn--sm ignis-topbar__new"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span class="ignis-topbar__new-label">Neu</span> <i class="fa-solid fa-chevron-down ignis-menu__caret" aria-hidden="true"></i></summary>
            <div class="ignis-menu__panel" role="menu">
                <?php foreach ($topActions as $topAction): ?>
                    <?php if ($topAction['type'] === 'drawer' || $topAction['type'] === 'link'): ?>
                        <?php // drawer: das Formular öffnet neben der Seite (drawer-form.js), ohne JS als Seite. ?>
                        <a href="<?= htmlspecialchars($topAction['target'], ENT_QUOTES) ?>" class="ignis-menu__item" role="menuitem"<?= $topAction['type'] === 'drawer' ? ' data-ignis-drawer' : '' ?>><i class="<?= htmlspecialchars($topAction['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($topAction['label']) ?></a>
                    <?php else: ?>
                        <button type="button" class="ignis-menu__item" role="menuitem"
                            data-quick-action-type="<?= htmlspecialchars($topAction['type'], ENT_QUOTES) ?>"
                            data-quick-action-target="<?= htmlspecialchars($topAction['target'], ENT_QUOTES) ?>"
                            data-quick-action-parent="<?= htmlspecialchars($topAction['parent'], ENT_QUOTES) ?>"><i class="<?= htmlspecialchars($topAction['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($topAction['label']) ?></button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ($topLoggedIn): ?>
        <details class="ignis-menu ignis-menu--right" data-ignis-menu>
            <summary class="ignis-topbar__user" aria-label="Kontomenü">
                <span class="ignis-topbar__avatar" aria-hidden="true"><?= htmlspecialchars($topInitials) ?></span>
                <span class="ignis-topbar__username"><?= htmlspecialchars($topUsername) ?></span>
                <span class="ignis-topbar__badge notification-poll-badge"<?= $topUnread > 0 ? '' : ' hidden' ?>><?= $topUnread > 9 ? '9+' : $topUnread ?></span>
                <i class="fa-solid fa-chevron-down ignis-menu__caret" aria-hidden="true"></i>
            </summary>
            <div class="ignis-menu__panel" role="menu">
                <div class="ignis-menu__identity">
                    <span class="ignis-topbar__avatar" aria-hidden="true"><?= htmlspecialchars($topInitials) ?></span>
                    <span class="ignis-menu__identity-text">
                        <span class="ignis-menu__identity-name"><?= htmlspecialchars($topUsername) ?></span>
                        <span class="ignis-menu__identity-role"><span class="ignis-menu__role-dot" style="background:<?= $topRoleColor ?>"></span><?= htmlspecialchars($topRoleName) ?></span>
                    </span>
                </div>
                <div class="ignis-menu__sep"></div>
                <a href="<?= htmlspecialchars($topBasePath . 'notifications/index', ENT_QUOTES) ?>" class="ignis-menu__item" role="menuitem">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i> Benachrichtigungen
                    <span class="ignis-menu__count notification-poll-badge"<?= $topUnread > 0 ? '' : ' hidden' ?>><?= $topUnread > 9 ? '9+' : $topUnread ?></span>
                </a>
                <div class="ignis-menu__sep"></div>
                <div class="ignis-menu__heading">Darstellung</div>
                <?php // ProfileController::theme() speichert und leitet hierher zurück. ?>
                <form method="POST" action="<?= htmlspecialchars($topBasePath . 'profile/theme', ENT_QUOTES) ?>" class="ignis-menu__form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken(), ENT_QUOTES) ?>">
                    <?php foreach ($topThemes as $topThemeKey => [$topThemeLabel, $topThemeIcon]): ?>
                        <button type="submit" name="theme" value="<?= $topThemeKey ?>" class="ignis-menu__item<?= $topTheme === $topThemeKey ? ' is-current' : '' ?>" role="menuitemradio" aria-checked="<?= $topTheme === $topThemeKey ? 'true' : 'false' ?>">
                            <i class="fa-solid fa-<?= $topThemeIcon ?>" aria-hidden="true"></i> <?= htmlspecialchars($topThemeLabel) ?>
                            <?php if ($topTheme === $topThemeKey): ?><i class="fa-solid fa-check ignis-menu__check" aria-hidden="true"></i><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </form>
                <div class="ignis-menu__sep"></div>
                <a href="<?= htmlspecialchars($topBasePath . 'logout', ENT_QUOTES) ?>" class="ignis-menu__item" role="menuitem"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Abmelden</a>
            </div>
        </details>
    <?php else: ?>
        <a href="<?= htmlspecialchars($topBasePath . 'login', ENT_QUOTES) ?>" class="ignis-btn ignis-btn--sm"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Anmelden</a>
    <?php endif; ?>
</header>

<?php if ($topLoggedIn): ?>
    <?php // Palette: Ziele und Schnellaktionen von oben, dazu die Treffer der Suche (global-search.js). ?>
    <div class="global-search-overlay" id="globalSearchOverlay" role="dialog" aria-modal="true" aria-labelledby="globalSearchLabel"
        data-base-path="<?= htmlspecialchars($topBasePath, ENT_QUOTES) ?>"
        data-endpoint="<?= htmlspecialchars($topBasePath . 'api/system/global-search', ENT_QUOTES) ?>"
        data-commands="<?= htmlspecialchars((string) json_encode($topCommands, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
        <div class="global-search-modal">
            <div class="gsm-input-wrap">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <label for="globalSearchInput" class="sr-only" id="globalSearchLabel">Suche</label>
                <input type="text" id="globalSearchInput" placeholder="Navigieren, suchen oder Schnellaktion starten …" autocomplete="off" />
                <span class="gsm-shortcut">ESC</span>
            </div>
            <div class="gsm-results" id="globalSearchResults"></div>
            <div class="gsm-footer">
                <span><kbd>&uarr;</kbd> <kbd>&darr;</kbd> Navigation</span>
                <span><kbd>Enter</kbd> &Ouml;ffnen</span>
                <span><kbd>Esc</kbd> Schlie&szlig;en</span>
            </div>
        </div>
    </div>
<?php endif; ?>
