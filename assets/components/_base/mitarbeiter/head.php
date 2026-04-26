<?php
require_once __DIR__ . '/../../../config/config.php';
$SITE_TITLE = isset($SITE_TITLE) ? $SITE_TITLE : 'Administration';
?>
<meta charset="UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo $SITE_TITLE; ?> &rsaquo; <?php echo SYSTEM_NAME ?></title>
<!-- Preload critical font -->
<link rel="preload" href="<?= BASE_PATH ?>assets/fonts/mavenpro/font/maven-pro-v39-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
<!-- Stylesheets: Bootstrap first, then overrides -->
<!-- Vendor + App-SCSS zuerst, Tailwind-Utilities zuletzt damit sie bei
     gleicher Spezifität im Cascade-Tie gewinnen. -->
<link rel="stylesheet" href="<?= asset('public/assets/dist/vendor.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/bootstrap-compat.min.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.css" />
<link rel="stylesheet" href="<?= asset('assets/css/style.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/admin.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/personal.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/ui.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('public/assets/dist/tailwind.css') ?>">
<script>
// Akzentfarbe sofort aus localStorage anwenden (kein Flackern)
(function(){var a=localStorage.getItem('intra_theme_accent');if(!a)return;var p={red:{m:'#d10000',d:'#660000'},blue:{m:'#2563eb',d:'#1e40af'},green:{m:'#16a34a',d:'#15803d'},purple:{m:'#7c3aed',d:'#6d28d9'},orange:{m:'#ea580c',d:'#c2410c'},teal:{m:'#0d9488',d:'#0f766e'},pink:{m:'#db2777',d:'#be185d'},amber:{m:'#d97706',d:'#b45309'}};var mc,dc;if(p[a]){mc=p[a].m;dc=p[a].d;}else if(/^#[0-9a-fA-F]{6}$/.test(a)){mc=a;var r=parseInt(a.slice(1,3),16),g=parseInt(a.slice(3,5),16),b=parseInt(a.slice(5,7),16);dc='#'+[r,g,b].map(function(c){return Math.max(0,Math.round(c*0.65)).toString(16).padStart(2,'0');}).join('');}else return;var rgb=parseInt(mc.slice(1,3),16)+', '+parseInt(mc.slice(3,5),16)+', '+parseInt(mc.slice(5,7),16);var s=document.documentElement.style;s.setProperty('--main-color',mc);s.setProperty('--main-color-dimmed',dc);s.setProperty('--main-color-rgb',rgb);s.setProperty('--fw-red',mc);})();
</script>
<!-- Core-Bundle: jQuery + Bootstrap + DataTables (synchron, wegen window.$-Nutzung in Inline-Scripts) -->
<script src="<?= asset('public/assets/dist/vendor.js') ?>"></script>
<!-- App scripts: defer to unblock rendering -->
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/dialog.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/dropdown.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/form.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/tabs.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/accordion.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/datepicker.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/chip.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/combobox.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/colorpicker.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/tooltip.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/alert.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/drawer.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/file.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/modules/datatables-config.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/snackbar.js"></script>
<!-- Favicon -->
<link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg" />
<link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_PATH ?>assets/favicon/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="<?php echo SYSTEM_NAME ?>" />
<link rel="manifest" href="<?= BASE_PATH ?>assets/favicon/site.webmanifest" />
<!-- Metas -->
<meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
<meta property="og:site_name" content="<?php echo SERVER_NAME ?>" />
<meta property="og:url" content="https://<?php echo SYSTEM_URL . BASE_PATH ?>dashboard.php" />
<meta property="og:title" content="<?php echo SYSTEM_NAME ?> - Intranet <?php echo SERVER_CITY ?>" />
<meta property="og:image" content="<?php echo META_IMAGE_URL ?>" />
<meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />