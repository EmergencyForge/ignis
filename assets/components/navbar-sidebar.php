<?php

/**
 * Sidebar — Icon-Rail links + aufklappbares Flyout-Panel daneben.
 *
 * Rendert ausschließlich die Sidebar. Topbar, Notifications-Flyout,
 * User-Dropdown und globale Modals bleiben in navbar.php.
 *
 * Datenquelle: config/navigation.php
 *
 * @var \PDO $pdo  Vom Aufrufer (navbar.php) gesetzt.
 */

use App\Auth\Permissions;

$navigationConfig = require __DIR__ . '/../../config/navigation.php';

/**
 * Filtert Rail/Sections/Items rekursiv nach Permissions.
 * Entfernt leere Sections/Rails, wenn nichts mehr sichtbar ist.
 */
$filterNavigation = static function (array $rail): array {
    $result = [];
    foreach ($rail as $item) {
        if (!empty($item['permissions']) && !Permissions::check($item['permissions'])) {
            continue;
        }
        if (!empty($item['sections'])) {
            $visibleSections = [];
            foreach ($item['sections'] as $section) {
                if (!empty($section['permissions']) && !Permissions::check($section['permissions'])) {
                    continue;
                }
                $visibleItems = [];
                foreach ($section['items'] ?? [] as $subItem) {
                    if (!empty($subItem['permissions']) && !Permissions::check($subItem['permissions'])) {
                        continue;
                    }
                    $visibleItems[] = $subItem;
                }
                if (!empty($visibleItems)) {
                    $section['items'] = $visibleItems;
                    $visibleSections[] = $section;
                }
            }
            if (empty($visibleSections)) {
                continue;
            }
            $item['sections'] = $visibleSections;
        }
        $result[] = $item;
    }
    return $result;
};

$rail = $filterNavigation($navigationConfig['rail'] ?? []);
?>

<aside class="intra-sidebar intra-sidebar--a16" id="intraSidebar" data-navbar-variant="a16">

    <!-- Rail (Icons) -->
    <div class="rail">
        <a href="<?= BASE_PATH ?>index" class="rail-logo" aria-label="<?= htmlspecialchars(SYSTEM_NAME) ?>">
            <svg viewBox="0 0 160 56" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= htmlspecialchars(SYSTEM_NAME) ?>">
                <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="Geist, system-ui, sans-serif" font-weight="800" font-style="italic" font-size="42" letter-spacing="-0.02em" fill="currentColor">ıgnıs</text>
            </svg>
        </a>

        <nav class="rail-nav" aria-label="Hauptnavigation">
            <?php foreach ($rail as $item):
                $hasFlyout = !empty($item['sections']);
                $dataPage = $item['data_page'] ?? $item['id'];
                $hrefAttr = $hasFlyout ? '#' : htmlspecialchars($item['href'] ?? '#');
                $roleAttr = $hasFlyout ? 'button' : 'link';
                $quickAction = $item['quick_action'] ?? null;
            ?>
                <div class="rail-item-wrap">
                    <a
                        href="<?= $hrefAttr ?>"
                        class="rail-item"
                        data-nav-id="<?= htmlspecialchars($item['id']) ?>"
                        data-page="<?= htmlspecialchars($dataPage) ?>"
                        <?= $hasFlyout ? 'data-flyout-trigger="true" role="button" aria-haspopup="true" aria-expanded="false"' : '' ?>
                        aria-label="<?= htmlspecialchars($item['label']) ?>"
                    >
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span class="rail-item-label"><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                    <?php if (!$hasFlyout && $quickAction !== null): ?>
                        <button
                            type="button"
                            class="rail-quick-action"
                            data-quick-action-type="<?= htmlspecialchars($quickAction['type']) ?>"
                            data-quick-action-target="<?= htmlspecialchars($quickAction['target']) ?>"
                            data-quick-action-parent="<?= htmlspecialchars($item['href'] ?? '') ?>"
                            aria-label="<?= htmlspecialchars($quickAction['label']) ?>"
                            title="<?= htmlspecialchars($quickAction['label']) ?>"
                        >
                            <i class="<?= htmlspecialchars($quickAction['icon'] ?? 'fa-solid fa-plus') ?>"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Flyout Panels -->
    <?php foreach ($rail as $item): if (empty($item['sections'])) continue; ?>
        <div
            class="flyout"
            data-flyout-for="<?= htmlspecialchars($item['id']) ?>"
            role="region"
            aria-label="<?= htmlspecialchars($item['label']) ?>"
            hidden
        >
            <div class="flyout-header">
                <span class="flyout-title"><?= htmlspecialchars($item['label']) ?></span>
            </div>

            <div class="flyout-body">
                <?php foreach ($item['sections'] as $sectionIdx => $section): ?>
                    <div class="flyout-section">
                        <?php if (!empty($section['label'])): ?>
                            <span class="flyout-section-title"><?= htmlspecialchars($section['label']) ?></span>
                        <?php endif; ?>
                        <?php foreach ($section['items'] as $subItem):
                            $isExternal = !empty($subItem['external']);
                            $qa = $subItem['quick_action'] ?? null;
                        ?>
                            <div class="flyout-item-wrap">
                                <a
                                    href="<?= htmlspecialchars($subItem['href']) ?>"
                                    class="flyout-item"
                                    <?= $isExternal ? 'target="_blank" rel="noopener"' : '' ?>
                                    data-href="<?= htmlspecialchars($subItem['href']) ?>"
                                >
                                    <span class="flyout-item-label"><?= htmlspecialchars($subItem['label']) ?></span>
                                    <?php if ($isExternal): ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square flyout-item-external"></i>
                                    <?php endif; ?>
                                </a>
                                <?php if ($qa !== null): ?>
                                    <button
                                        type="button"
                                        class="flyout-quick-action"
                                        data-quick-action-type="<?= htmlspecialchars($qa['type']) ?>"
                                        data-quick-action-target="<?= htmlspecialchars($qa['target']) ?>"
                                        data-quick-action-parent="<?= htmlspecialchars($subItem['href']) ?>"
                                        aria-label="<?= htmlspecialchars($qa['label']) ?>"
                                        title="<?= htmlspecialchars($qa['label']) ?>"
                                    >
                                        <i class="<?= htmlspecialchars($qa['icon'] ?? 'fa-solid fa-plus') ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

</aside>
<div class="intra-sidebar-backdrop" id="intraSidebarBackdrop" hidden></div>
