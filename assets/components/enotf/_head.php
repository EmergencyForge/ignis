<?php
require_once __DIR__ . '/../../config/config.php';
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
<link rel="stylesheet" href="<?= asset('assets/css/divi.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/ui.min.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/enotf-modals.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/enotf-toast.css') ?>">
<link rel="stylesheet" href="<?= asset('public/assets/dist/tailwind.css') ?>">
<!-- Core-Bundle: jQuery + Bootstrap + DataTables (synchron, wegen window.$-Nutzung in Inline-Scripts) -->
<script src="<?= asset('public/assets/dist/vendor.js') ?>"></script>
<!-- App scripts: defer to unblock rendering -->
<script defer src="<?= BASE_PATH ?>assets/js/toasts.js"></script>
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
<script defer src="<?= BASE_PATH ?>assets/js/force-24h-time.js"></script>
<script defer src="<?= BASE_PATH ?>assets/js/force-german-date.js"></script>
<script defer src="<?= BASE_PATH ?>assets/js/enotf-session-sync.js?v=<?= filemtime(__DIR__ . '/../../js/enotf-session-sync.js') ?>"></script>
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
<meta property="og:url" content="<?= $prot_url ?>" />
<meta property="og:title" content="<?php echo SYSTEM_NAME ?> - Intranet <?php echo SERVER_CITY ?>" />
<meta property="og:image" content="https://<?php echo SYSTEM_URL ?>/assets/img/aelrd.png" />
<meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
<!-- CitizenFX: Session-ID an FiveM-Client senden -->
<script>
(function() {
    if (navigator.userAgent.includes('CitizenFX')) {
        fetch('<?= BASE_PATH ?>api/character/get-session-id.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.session_id) {
                    // An parent senden (NUI-Seite), falls im iframe — sonst an eigenes window
                    var target = (window.parent !== window) ? window.parent : window;
                    target.postMessage({ type: 'ignis_session', session_id: data.session_id }, '*');
                }
            })
            .catch(function() {});
    }
})();
</script>