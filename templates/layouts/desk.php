<?php
/**
 * templates/layouts/desk.php: Desktop-Hülle (efOS) statt der klassischen
 * Seitenhülle.
 *
 * App\Helpers\Layout::render() wählt dieses Layout statt `admin`, wenn das
 * Gerät eine andere Oberfläche als „Klassisch“ will (App\Support\DeskShell).
 * Die angefragte Ansicht steht als erstes Fenster drin, Menüleiste, Dock und
 * alle weiteren Fenster baut desk.js aus dem ui-Paket.
 *
 *   @var string $SITE_TITLE     Seitentitel der Ansicht (head.php)
 *   @var string $layoutFragment die Ansicht als Fragment (Layout::fragment())
 *   @var string $layoutUrl      angefragte Adresse
 *
 * Das Suchfeld ist versteckt und nur für die Kommandopalette da
 * (assets/js/ui/palette.js hängt sich daran), die Menüleiste öffnet sie.
 * Flash-Meldungen trägt das Fragment, nicht die Hülle.
 */

use App\Helpers\Navigation;
use App\Support\DeskShell;

$layoutThemeFade = \App\Helpers\Theme::consumeTransition();
$layoutTheme = \App\Helpers\Theme::mode();
$deskPalette = Navigation::palette(Navigation::groups());
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($layoutTheme) ?>" data-efe-icons="fontawesome" data-efe-tooltip="data-ignis-tooltip">

<head>
    <?php if ($layoutThemeFade): ?><style>@media (prefers-reduced-motion: no-preference) { @view-transition { navigation: auto; } }</style><?php endif; ?>
    <?= \App\Helpers\Theme::systemScript() ?>
    <?php require dirname(__DIR__, 2) . '/assets/components/_base/admin/head.php'; ?>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/desk.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/drawer-form.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/workbench.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/palette.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/breadcrumb.js"></script>
</head>

<body class="ignis-desk-body" data-ui-skin="core" data-preference-key="<?= htmlspecialchars((string) ($_SESSION['userid'] ?? 'local'), ENT_QUOTES) ?>">
    <div class="ignis-topbar__search ignis-search" role="search" hidden data-endpoint="<?= htmlspecialchars(BASE_PATH . 'api/system/global-search', ENT_QUOTES) ?>" data-ignis-actions="<?= htmlspecialchars((string) json_encode($deskPalette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
        <input type="search" data-ignis-global-search aria-label="Suche" autocomplete="off">
    </div>
    <?= DeskShell::render($layoutUrl, $layoutFragment) ?>
</body>

</html>
