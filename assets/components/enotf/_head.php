<?php
require_once __DIR__ . '/../../config/config.php';
$SITE_TITLE = isset($SITE_TITLE) ? $SITE_TITLE : 'Administration';
?>
<meta charset="UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo $SITE_TITLE; ?> &rsaquo; <?php echo SYSTEM_NAME ?></title>
<!-- Stylesheets -->
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/divi.min.css" />
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
<link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
<script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
<script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= BASE_PATH ?>vendor/fortawesome/font-awesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-modals.css">
<script src="<?= BASE_PATH ?>assets/js/dialogs.js"></script>
<script src="<?= BASE_PATH ?>assets/js/enotf-custom-dropdown.js"></script>
<script src="<?= BASE_PATH ?>assets/js/force-24h-time.js"></script>
<script src="<?= BASE_PATH ?>assets/js/force-german-date.js"></script>
<script src="<?= BASE_PATH ?>assets/js/enotf-session-sync.js?v=<?= filemtime(__DIR__ . '/../../js/enotf-session-sync.js') ?>"></script>
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
                    target.postMessage({ type: 'intraRP_session', session_id: data.session_id }, '*');
                }
            })
            .catch(function() {});
    }
})();
</script>