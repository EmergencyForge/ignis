<?php

/**
 * Settings/System — Landing-Page (Karten-Grid).
 *
 * Loest den frueheren Updater-View ab, der hier inline lag (jetzt in
 * `updater.php`). Diese Seite ist der zentrale Einstieg in alle System-
 * Verwaltungs-Sub-Seiten: Updater, Config, Performance, Telemetry, Logs,
 * Cron. Spaeter als Anker fuer den Module-Manager-Block (siehe Modulare-
 * Architektur-Roadmap).
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\SystemUpdater;

$SITE_TITLE = 'System';

if (!Permissions::check(['admin'])) {
    header('Location: ' . BASE_PATH . 'index');
    exit;
}

/** Versions-Info aus storage/version.json (best-effort, fehlt wenn frisch) */
$versionFile = dirname(__DIR__, 3) . '/storage/version.json';
$versionInfo = is_file($versionFile)
    ? (array) (json_decode((string) file_get_contents($versionFile), true) ?? [])
    : [];
$currentVersion = (string) ($versionInfo['version'] ?? 'unbekannt');
$buildNumber    = (string) ($versionInfo['build_number'] ?? '');
$lastUpdate     = (string) ($versionInfo['updated_at'] ?? '');

$automaticUpdateInfo = null;
try {
    $automaticUpdateInfo = (new SystemUpdater())->checkForUpdatesCached();
} catch (\Throwable) {
    // Die Systemübersicht bleibt auch ohne ausgehende Verbindung nutzbar.
}

$cards = [
    [
        'href' => BASE_PATH . 'settings/system/updater',
        'icon' => 'fa-solid fa-arrow-up-from-bracket',
        'title' => 'Updater',
        'desc' => 'Auf neue Releases prüfen, Updates installieren, Branches wechseln.',
        'accent' => 'var(--main-color)',
        'badge' => !empty($automaticUpdateInfo['available'])
            ? 'Neu: ' . (string) ($automaticUpdateInfo['latest_version'] ?? 'Update')
            : null,
    ],
    [
        'href' => BASE_PATH . 'settings/system/config',
        'icon' => 'fa-solid fa-sliders',
        'title' => 'Konfiguration',
        'desc' => 'System-Daten, API-Keys, Brand-Identität.',
    ],
    [
        'href' => BASE_PATH . 'settings/system/plugins',
        'icon' => 'fa-solid fa-puzzle-piece',
        'title' => 'Plugins',
        'desc' => 'Module aktivieren/deaktivieren, Community-Plugins installieren.',
    ],
    [
        'href' => BASE_PATH . 'settings/system/performance',
        'icon' => 'fa-solid fa-gauge-high',
        'title' => 'Performance',
        'desc' => 'Request-Metriken, Slow-Query-Log, PHP-OpCache-Status.',
    ],
    [
        'href' => BASE_PATH . 'settings/system/telemetry',
        'icon' => 'fa-solid fa-chart-line',
        'title' => 'Telemetrie',
        'desc' => 'Anonyme Statistiken, globale Ankündigungen.',
    ],
    [
        'href' => BASE_PATH . 'settings/system/logs',
        'icon' => 'fa-solid fa-rectangle-list',
        'title' => 'Logs',
        'desc' => 'Error-Log-Viewer, Volltext-Suche, Inbox.',
    ],
    [
        'href' => BASE_PATH . 'settings/system/cron',
        'icon' => 'fa-solid fa-clock-rotate-left',
        'title' => 'Cron',
        'desc' => 'Geplante Jobs, manuell ausführen, History.',
    ],
];
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-theme="dark" data-page="settings-system">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="twplus-page">
            <header class="twplus-page-header">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Administration</p>
                    <h1>System</h1>
                    <p class="twplus-page-header__description">Wartung, Konfiguration und Diagnostik des ıgnıs-Systems.</p>
                </div>
            </header>

            <?php Flash::render(); ?>

            <div class="twplus-settings-layout">
                <?php include __DIR__ . '/../../../assets/components/settings/system/_navigation.php'; ?>
                <main>
                    <dl class="twplus-stats" aria-label="Systeminformationen">
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">Version</dt>
                            <dd class="twplus-stats__value text-base"><?= htmlspecialchars($currentVersion) ?></dd>
                        </div>
                        <?php if ($buildNumber !== ''): ?>
                            <div class="twplus-stats__item">
                                <dt class="twplus-stats__label">Build</dt>
                                <dd class="twplus-stats__value text-base"><?= htmlspecialchars($buildNumber) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($lastUpdate !== ''): ?>
                            <div class="twplus-stats__item">
                                <dt class="twplus-stats__label">Letztes Update</dt>
                                <dd class="twplus-stats__value text-base"><?= htmlspecialchars($lastUpdate) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="twplus-stats__item">
                            <dt class="twplus-stats__label">PHP</dt>
                            <dd class="twplus-stats__value text-base"><?= htmlspecialchars(PHP_VERSION) ?></dd>
                        </div>
                    </dl>

                    <div class="twplus-link-grid">
                        <?php foreach ($cards as $card): ?>
                            <a href="<?= htmlspecialchars($card['href']) ?>"
                               class="twplus-link-card">
                                <span class="twplus-link-card__icon"><i class="<?= htmlspecialchars($card['icon']) ?>" aria-hidden="true"></i></span>
                                <span class="twplus-link-card__body">
                                    <span class="twplus-link-card__title"><?= htmlspecialchars($card['title']) ?></span>
                                    <?php if (!empty($card['badge'])): ?>
                                        <span class="ignis-chip ignis-chip--warning mt-1"><i class="fa-solid fa-arrow-up"></i> <?= htmlspecialchars($card['badge']) ?></span>
                                    <?php endif; ?>
                                    <span class="twplus-link-card__description"><?= htmlspecialchars($card['desc']) ?></span>
                                </span>
                                <i class="fa-solid fa-chevron-right twplus-link-card__arrow" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
