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

$cards = [
    [
        'href' => BASE_PATH . 'settings/system/updater',
        'icon' => 'fa-solid fa-arrow-up-from-bracket',
        'title' => 'Updater',
        'desc' => 'Auf neue Releases prüfen, Updates installieren, Branches wechseln.',
        'accent' => 'var(--main-color)',
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
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
    <style>
        .system-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            background: var(--body-bg-lighter, #161616);
            border: 1px solid var(--darkgray, #2a2a2a);
            border-radius: var(--radius-md, 6px);
            color: inherit;
            text-decoration: none;
            transition: border-color 0.15s, transform 0.15s, background 0.15s;
        }
        .system-card:hover {
            border-color: var(--main-color, #ff4d00);
            background: rgba(var(--main-color-rgb, 255, 77, 0), 0.04);
            text-decoration: none;
            transform: translateY(-1px);
        }
        .system-card__icon {
            font-size: 1.5rem;
            color: var(--text-dimmed, #818189);
            min-width: 2rem;
        }
        .system-card--primary .system-card__icon {
            color: var(--main-color, #ff4d00);
        }
        .system-card__title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.25rem;
            color: var(--text-title, #fff);
        }
        .system-card__desc {
            font-size: 0.82rem;
            color: var(--text-dimmed, #818189);
            margin: 0;
            line-height: 1.4;
        }
        .system-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .system-meta__item {
            padding: 0.75rem 1rem;
            background: var(--body-bg-lighter, #161616);
            border: 1px solid var(--darkgray, #2a2a2a);
            border-radius: var(--radius-sm, 4px);
        }
        .system-meta__label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dimmed, #818189);
            margin-bottom: 0.2rem;
        }
        .system-meta__value {
            font-family: 'Geist Mono', monospace;
            font-size: 0.88rem;
            color: var(--text-title, #fff);
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings-system">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container mx-auto">
            <h1>System</h1>
            <p class="text-gray-400">Wartung, Konfiguration und Diagnostik des ıgnıs-Systems.</p>

            <?php Flash::render(); ?>

            <div class="system-meta">
                <div class="system-meta__item">
                    <div class="system-meta__label">Version</div>
                    <div class="system-meta__value"><?= htmlspecialchars($currentVersion) ?></div>
                </div>
                <?php if ($buildNumber !== ''): ?>
                    <div class="system-meta__item">
                        <div class="system-meta__label">Build</div>
                        <div class="system-meta__value"><?= htmlspecialchars($buildNumber) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($lastUpdate !== ''): ?>
                    <div class="system-meta__item">
                        <div class="system-meta__label">Letztes Update</div>
                        <div class="system-meta__value"><?= htmlspecialchars($lastUpdate) ?></div>
                    </div>
                <?php endif; ?>
                <div class="system-meta__item">
                    <div class="system-meta__label">PHP</div>
                    <div class="system-meta__value"><?= htmlspecialchars(PHP_VERSION) ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($cards as $card): ?>
                    <a href="<?= htmlspecialchars($card['href']) ?>"
                       class="system-card<?= isset($card['accent']) ? ' system-card--primary' : '' ?> no-underline hover:no-underline">
                        <i class="<?= htmlspecialchars($card['icon']) ?> system-card__icon"></i>
                        <div>
                            <h3 class="system-card__title"><?= htmlspecialchars($card['title']) ?></h3>
                            <p class="system-card__desc"><?= htmlspecialchars($card['desc']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
