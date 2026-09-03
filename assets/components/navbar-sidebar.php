<?php

declare(strict_types=1);

/**
 * assets/components/navbar-sidebar.php — Sidebar-Navigation.
 *
 * Gruppen und Einträge aus App\Helpers\Navigation (config/navigation.php
 * plus Plugin-Fragmente, nach Rechten gefiltert, aktiver Eintrag per
 * Pfadvergleich). Ein Eintrag mit Schnellaktion bekommt ein Plus an der
 * Zeile, sichtbar beim Überfahren oder per Tastatur: für `drawer` und
 * `link` ein Link auf das Formular (drawer-form.js öffnet es im Drawer),
 * für `modal` ein Knopf, den shell.js als CustomEvent `quick-action:<target>`
 * ausführt.
 *
 * 240 px breit, eingeklappt 56 px: dann bleiben die Symbole mit Tooltip
 * (title), Labels und Gruppen verschwinden (CSS über html.is-collapsed,
 * Zustand hält shell.js in localStorage). Unter 900 px ist sie ein
 * Drawer hinter dem Menü-Knopf der Topbar, mit Scrim.
 *
 * Läuft per `require` im Scope des Layouts (oder des Shims navbar.php) und
 * teilt dessen Variablen. Alle lokalen Variablen tragen darum das Präfix
 * `nav`; tests/Unit/Templates/SidebarScopeTest.php hält das fest.
 */

use App\Helpers\Navigation;

$navGroups = Navigation::groups();

$navVersionFile = dirname(__DIR__, 2) . '/storage/version.json';
$navVersionInfo = is_file($navVersionFile) ? json_decode((string) file_get_contents($navVersionFile), true) : null;
$navVersion = is_array($navVersionInfo) && !empty($navVersionInfo['version']) ? (string) $navVersionInfo['version'] : null;
?>
<aside class="ignis-sidebar" id="ignisSidebar" aria-label="Hauptnavigation">
    <nav class="ignis-sidebar__nav">
        <?php foreach ($navGroups as $navGroup): ?>
            <?php if (!empty($navGroup['label'])): ?>
                <div class="ignis-sidebar__group"><?= htmlspecialchars((string) $navGroup['label']) ?></div>
            <?php endif; ?>
            <?php foreach ($navGroup['items'] as $navItem):
                $navQuick = is_array($navItem['quick_action'] ?? null) ? $navItem['quick_action'] : null;
                $navExternal = !empty($navItem['external']);
            ?>
                <div class="ignis-sidebar__row<?= $navItem['active'] ? ' is-active' : '' ?>">
                    <a
                        href="<?= htmlspecialchars((string) $navItem['href'], ENT_QUOTES) ?>"
                        class="ignis-sidebar__link"
                        title="<?= htmlspecialchars((string) $navItem['label'], ENT_QUOTES) ?>"
                        <?= $navItem['active'] ? 'aria-current="page"' : '' ?>
                        <?= $navExternal ? 'target="_blank" rel="noopener"' : '' ?>
                    >
                        <i class="<?= htmlspecialchars((string) $navItem['icon']) ?>" aria-hidden="true"></i>
                        <span class="ignis-sidebar__label"><?= htmlspecialchars((string) $navItem['label']) ?></span>
                        <?php if ($navExternal): ?>
                            <i class="fa-solid fa-arrow-up-right-from-square ignis-sidebar__external" aria-hidden="true"></i>
                        <?php endif; ?>
                    </a>
                    <?php if ($navQuick !== null && isset($navQuick['type'], $navQuick['target'], $navQuick['label'])): ?>
                        <?php if ($navQuick['type'] === 'drawer' || $navQuick['type'] === 'link'): ?>
                            <?php // Ein Link: mit JS öffnet drawer-form.js das Formular im Drawer, ohne JS die Seite. ?>
                            <a
                                href="<?= htmlspecialchars((string) $navQuick['target'], ENT_QUOTES) ?>"
                                class="ignis-sidebar__quick"
                                <?= $navQuick['type'] === 'drawer' ? 'data-ignis-drawer' : '' ?>
                                aria-label="<?= htmlspecialchars((string) $navQuick['label'], ENT_QUOTES) ?>"
                                title="<?= htmlspecialchars((string) $navQuick['label'], ENT_QUOTES) ?>"
                            >
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <button
                                type="button"
                                class="ignis-sidebar__quick"
                                data-quick-action-type="<?= htmlspecialchars((string) $navQuick['type'], ENT_QUOTES) ?>"
                                data-quick-action-target="<?= htmlspecialchars((string) $navQuick['target'], ENT_QUOTES) ?>"
                                data-quick-action-parent="<?= htmlspecialchars((string) $navItem['href'], ENT_QUOTES) ?>"
                                aria-label="<?= htmlspecialchars((string) $navQuick['label'], ENT_QUOTES) ?>"
                                title="<?= htmlspecialchars((string) $navQuick['label'], ENT_QUOTES) ?>"
                            >
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <?php if ($navVersion !== null): ?>
        <div class="ignis-sidebar__version" title="ıgnıs <?= htmlspecialchars($navVersion, ENT_QUOTES) ?>">
            <span class="ignis-sidebar__version-mark" aria-hidden="true">ı</span>
            <span class="ignis-sidebar__label">ıgnıs <?= htmlspecialchars($navVersion) ?></span>
        </div>
    <?php endif; ?>
</aside>
<div class="ignis-sidebar-scrim" data-ignis-nav-close hidden></div>
