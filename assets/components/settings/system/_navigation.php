<?php

/**
 * Shared system-settings navigation, adapted from the Tailwind Plus
 * settings-screen pattern. The current entry is inferred from the request
 * path so the partial stays controller-independent.
 */

$systemSettingsPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$systemSettingsItems = [
    ['href' => BASE_PATH . 'settings/system/index',       'match' => '/settings/system/index',       'icon' => 'fa-solid fa-house',                  'label' => 'Übersicht'],
    ['href' => BASE_PATH . 'settings/system/config',      'match' => '/settings/system/config',      'icon' => 'fa-solid fa-sliders',                'label' => 'Konfiguration'],
    ['href' => BASE_PATH . 'settings/system/plugins',     'match' => '/settings/system/plugins',     'icon' => 'fa-solid fa-puzzle-piece',           'label' => 'Plugins'],
    ['href' => BASE_PATH . 'settings/system/updater',     'match' => '/settings/system/updater',     'icon' => 'fa-solid fa-arrow-up-from-bracket',  'label' => 'Updates'],
    ['href' => BASE_PATH . 'settings/system/performance', 'match' => '/settings/system/performance', 'icon' => 'fa-solid fa-gauge-high',             'label' => 'Performance'],
    ['href' => BASE_PATH . 'settings/system/telemetry',   'match' => '/settings/system/telemetry',   'icon' => 'fa-solid fa-chart-line',             'label' => 'Telemetrie'],
    ['href' => BASE_PATH . 'settings/system/logs',        'match' => '/settings/system/logs',        'icon' => 'fa-solid fa-rectangle-list',         'label' => 'Logs'],
    ['href' => BASE_PATH . 'settings/system/cron',        'match' => '/settings/system/cron',        'icon' => 'fa-solid fa-clock-rotate-left',      'label' => 'Cronjobs'],
];
?>
<aside class="twplus-settings-nav" aria-label="Systemeinstellungen">
    <div class="twplus-settings-nav__title">System</div>
    <ul class="twplus-settings-nav__list">
        <?php foreach ($systemSettingsItems as $systemSettingsItem):
            $isSystemSettingsActive = str_contains($systemSettingsPath, $systemSettingsItem['match']);
        ?>
            <li>
                <a href="<?= htmlspecialchars($systemSettingsItem['href']) ?>"
                   class="twplus-settings-nav__link<?= $isSystemSettingsActive ? ' is-active' : '' ?>"
                   <?= $isSystemSettingsActive ? 'aria-current="page"' : '' ?>>
                    <i class="<?= htmlspecialchars($systemSettingsItem['icon']) ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($systemSettingsItem['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
