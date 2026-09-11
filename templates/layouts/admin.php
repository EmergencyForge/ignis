<?php
/**
 * templates/layouts/admin.php — Seitenhülle der eingeloggten Ansichten.
 *
 * App\Helpers\Layout::render() legt sie um eine Ansicht, die `$layout =
 * 'admin'` setzt (Controller::renderView()), und um die Root-Skripte
 * index.php und dashboard.php. Erwartet im Scope:
 *
 *   @var string      $SITE_TITLE     Seitentitel, gelesen von head.php
 *   @var string      $layoutBodyId   id des <body>
 *   @var string      $layoutBodyPage data-page des <body>
 *   @var string      $layoutContent  gerenderter Inhalt der Ansicht, landet im <main>
 *   @var string      $layoutHead     zusätzliches Markup für den <head> (Stylesheets, Inline-Styles)
 *
 * Aufbau: Topbar über die volle Breite, darunter Sidebar und Inhalt als
 * Raster (assets/css/_shell.scss). Der Darstellungsmodus des Kontos steht
 * als data-theme am <html>, „system" löst ein Inline-Script sofort auf.
 * Der eingeklappte Zustand der Sidebar liegt in localStorage unter
 * `ignis.sidebar` und kommt als Klasse ans <html>, bevor das erste
 * Stylesheet lädt, damit nichts springt; assets/js/ui/shell.js pflegt ihn.
 *
 * Die Flash-Meldung der Session wird hier oben im <main> ausgegeben
 * (App\Helpers\Flash::render(), Toast über snackbar.js); Ansichten rufen
 * render() nicht mehr selbst (tests/Unit/Templates/FlashRenderUsageTest.php).
 *
 * Topbar und Sidebar laufen in diesem Scope; ihre Variablen tragen die
 * Präfixe `top` und `nav` (tests/Unit/Templates/SidebarScopeTest.php).
 * head.php erkennt an $layoutTheme, dass die Hülle den Modus schon gesetzt
 * hat, und lässt ihr Inline-Script weg.
 */

$layoutTheme = \App\Helpers\Theme::mode();
$layoutPath  = \App\Helpers\Navigation::currentPath();
$layoutSystemNav = str_starts_with($layoutPath, '/settings/system/') && $layoutPath !== '/settings/system/index';
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($layoutTheme) ?>">

<head>
    <?= \App\Helpers\Theme::systemScript() ?>
    <script>try { if (localStorage.getItem('ignis.sidebar') === 'collapsed') document.documentElement.classList.add('is-collapsed'); } catch (e) {}</script>
    <?php require dirname(__DIR__, 2) . '/assets/components/_base/admin/head.php'; ?>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/shell.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/drawer-form.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/workbench.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/palette.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/navbar/notifications.js"></script>
    <?= $layoutHead ?>
</head>

<body id="<?= htmlspecialchars($layoutBodyId) ?>" class="ignis-app" data-page="<?= htmlspecialchars($layoutBodyPage) ?>">
    <?php require dirname(__DIR__, 2) . '/assets/components/topbar.php'; ?>
    <?php require dirname(__DIR__, 2) . '/assets/components/navbar-sidebar.php'; ?>
    <?php include dirname(__DIR__, 2) . '/assets/components/global-announcements.php'; ?>

    <main class="ignis-main">
        <?php \App\Helpers\Flash::render(); ?>
        <?php if ($layoutSystemNav): ?>
            <div class="twplus-page" style="padding-bottom:0;">
                <?php include dirname(__DIR__, 2) . '/assets/components/settings/system/_navigation.php'; ?>
            </div>
        <?php endif; ?>
<?= $layoutContent ?>
        <?php include dirname(__DIR__, 2) . '/assets/components/footer.php'; ?>
    </main>
</body>

</html>
