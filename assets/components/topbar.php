<?php

declare(strict_types=1);

/**
 * assets/components/topbar.php — Leiste über allen eingeloggten Ansichten.
 *
 * Links der Sidebar-Schalter und die Wortmarke (SYSTEM_LOGO, sonst die
 * ignis-Wortmarke in Textfarbe), in der Mitte das Suchfeld (Ctrl+K springt
 * hinein; beim Tippen öffnet palette.js die Palette darunter, gespeist aus
 * GET /api/system/global-search und den Aktionen in data-ignis-actions), rechts das
 * Neu-Menü mit den Schnellaktionen der Navigation, die der Betrachter
 * sehen darf, die Glocke mit dem Zähler der ungelesenen Benachrichtigungen
 * (das Popover lädt shell.js beim ersten Öffnen von GET /inbox/popover,
 * den Zähler hält notifications.js aktuell) und das Kontomenü mit
 * Darstellungswechsel und Abmelden. Die Menüs sind <details>, damit sie
 * ohne JS funktionieren; shell.js schließt sie bei Klick daneben.
 *
 * Läuft per `require` im Scope des Layouts (oder des Shims navbar.php);
 * lokale Variablen tragen das Präfix `top` (SidebarScopeTest).
 */

use App\Helpers\Navigation;
use App\Helpers\Theme;
use App\Security\CsrfProtection;
use App\Support\NavigationCounters;

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

// Glocke: ungelesene Benachrichtigungen, derselbe Zähler wie in der
// Sidebar (NavigationCounters::for('inbox'), je Request gecacht).
$topUnread = $topLoggedIn ? (int) (NavigationCounters::for('inbox') ?? 0) : 0;

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

// Einträge der Palette ohne Server (assets/js/ui/palette.js): „X anlegen"
// aus den Schnellaktionen und „Gehe zu" aus der sichtbaren Navigation,
// als JSON am Suchfeld. Die Treffer aus dem Datenbestand kommen von
// GET /api/system/global-search dazu.
$topPalette = [];
foreach ($topActions as $topAction) {
    $topPalette[] = [
        'label'    => $topAction['label'],
        'sub'      => 'Neu',
        'href'     => $topAction['type'] === 'modal'
            ? $topAction['parent'] . (str_contains($topAction['parent'], '?') ? '&' : '?') . 'action=create&quick=' . rawurlencode($topAction['target'])
            : $topAction['target'],
        'drawer'   => $topAction['type'] === 'drawer',
        'keywords' => 'neu anlegen erstellen',
    ];
}
foreach ($topGroups as $topGroup) {
    foreach ($topGroup['items'] as $topItem) {
        $topPalette[] = [
            'label'    => (string) $topItem['label'],
            'sub'      => 'Gehe zu',
            'href'     => (string) $topItem['href'],
            'drawer'   => false,
            'keywords' => 'gehe zu öffnen ' . (is_string($topGroup['label'] ?? null) ? $topGroup['label'] : ''),
        ];
    }
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
        <div class="ignis-topbar__search" role="search"
            data-endpoint="<?= htmlspecialchars($topBasePath . 'api/system/global-search', ENT_QUOTES) ?>"
            data-ignis-actions="<?= htmlspecialchars((string) json_encode($topPalette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="ignis-topbar__search-input" data-ignis-global-search placeholder="Suchen oder Schnellaktion starten" aria-label="Suche" autocomplete="off" aria-autocomplete="list" aria-expanded="false">
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
        <details class="ignis-menu ignis-menu--right ignis-topbar__bell" data-ignis-menu data-ignis-inbox="<?= htmlspecialchars($topBasePath . 'inbox/popover', ENT_QUOTES) ?>">
            <summary class="ignis-topbar__toggle" aria-label="Posteingang<?= $topUnread > 0 ? ', ' . $topUnread . ' ungelesen' : '' ?>" title="Posteingang">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                <span class="ignis-topbar__badge notification-poll-badge"<?= $topUnread > 0 ? '' : ' hidden' ?>><?= $topUnread > 99 ? '99+' : $topUnread ?></span>
            </summary>
            <div class="ignis-menu__panel ignis-inbox-popover" role="menu">
                <div class="ignis-inbox-popover__loading" aria-hidden="true"><span class="ignis-skeleton" style="width:50%"></span><span class="ignis-skeleton"></span><span class="ignis-skeleton" style="width:70%"></span></div>
                <noscript><a href="<?= htmlspecialchars($topBasePath . 'inbox', ENT_QUOTES) ?>" class="ignis-menu__item" role="menuitem"><i class="fa-solid fa-inbox" aria-hidden="true"></i> Posteingang öffnen</a></noscript>
            </div>
        </details>

        <details class="ignis-menu ignis-menu--right" data-ignis-menu>
            <summary class="ignis-topbar__user" aria-label="Kontomenü">
                <span class="ignis-topbar__avatar" aria-hidden="true"><?= htmlspecialchars($topInitials) ?></span>
                <span class="ignis-topbar__username"><?= htmlspecialchars($topUsername) ?></span>
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
