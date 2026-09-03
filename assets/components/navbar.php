<?php

/**
 * assets/components/navbar.php — Kompatibilitäts-Shim.
 *
 * Bis zum Redesign baute jede Seite ihre Hülle selbst und band hier eine
 * Datei mit 2600 Zeilen ein: Sidebar, Topbar, Suche, Benutzermenü, dazu
 * 1400 Zeilen CSS und 800 Zeilen JS inline. Das alles wohnt jetzt in der
 * Hülle (templates/layouts/admin.php mit topbar.php und navbar-sidebar.php,
 * Styles in assets/css/_shell.scss, Verhalten in assets/js/ui/shell.js,
 * assets/js/navbar/global-search.js und notifications.js).
 *
 * Dieser Include bleibt für Seiten, die ihr <html> noch selbst bauen und
 * ihn weiter einbinden — die Admin-Seiten von eNOTF. Sie bekommen
 * dieselben Komponenten; `ignis-app--legacy` am <body> schaltet die
 * Hülle von Raster auf feste Positionierung, weil der Inhalt hier kein
 * <main> hat, sondern als Geschwister hinter der Sidebar folgt.
 *
 * Neue und umgestellte Seiten setzen stattdessen `$layout = 'admin'`
 * (Controller::renderView()).
 */

$navbarPath = \App\Helpers\Navigation::currentPath();
$navbarSystemNav = str_starts_with($navbarPath, '/settings/system/') && $navbarPath !== '/settings/system/index';
?>
<script>
    document.body.classList.add('ignis-app', 'ignis-app--legacy');
    try { if (localStorage.getItem('ignis.sidebar') === 'collapsed') document.documentElement.classList.add('is-collapsed'); } catch (e) {}
</script>
<?php require __DIR__ . '/topbar.php'; ?>
<?php require __DIR__ . '/navbar-sidebar.php'; ?>
<?php include __DIR__ . '/global-announcements.php'; ?>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/shell.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/navbar/global-search.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/navbar/notifications.js"></script>
<?php if ($navbarSystemNav): ?>
    <div class="twplus-page" style="padding-bottom:0;">
        <?php include __DIR__ . '/settings/system/_navigation.php'; ?>
    </div>
<?php endif; ?>
