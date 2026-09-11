<?php

/**
 * eNOTF v2 — v1-Head für die Nicht-Protokoll-Seiten.
 *
 * Gleiches Asset-Set wie die v1-Seiten (assets/components/enotf/_head.php)
 * bzw. _layout-protokoll.php: Bootstrap (vendor-enotf), divi.css, admin/ui,
 * Tailwind-Utilities — nur ohne jQuery/DataTables (vendor.js), weil die
 * v2-Seiten komplett Vanilla laufen. Dazu die v2-Bausteine, die bleiben:
 * dialog.js/snackbar.js (Dialoge) und ev2-select.js (Ev2Select/Ev2Suggest
 * für den FiveM-CEF), letztere hier optisch auf den v1-Look des
 * enotf-custom-dropdown umgestylt.
 *
 * Verwendung (innerhalb von <head>):
 *   $__v1Title = 'eNOTF';           // Titel-Präfix (v1: $SITE_TITLE)
 *   require __DIR__ . '/_v1head.php';
 *
 * Hinweis: Ev2Select/Ev2Suggest initialisieren sich nur auf Seiten mit
 * body[data-page="enotf-v2"] — die einbindenden Templates setzen das
 * Attribut (v1-CSS hängt an IDs/Klassen, nicht an data-page-Werten,
 * deshalb kollidiert das mit nichts).
 */

$__v1Title = $__v1Title ?? 'eNOTF';
?>
<meta charset="UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($__v1Title, ENT_QUOTES) ?> &rsaquo; <?= SYSTEM_NAME ?></title>
<!-- Stylesheet-Reihenfolge wie v1 (_head.php): Bootstrap, dann Overrides -->
<link rel="stylesheet" href="<?= asset('public/assets/dist/vendor.css') ?>">
<link rel="stylesheet" href="<?= asset('public/assets/dist/vendor-enotf.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist/css/all.min.css" />
<link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist-mono/css/all.min.css" />
<link rel="stylesheet" href="<?= asset('public/assets/dist/divi.css') ?>" />
<link rel="stylesheet" href="<?= asset('public/assets/dist/admin.css') ?>" />
<link rel="stylesheet" href="<?= asset('public/assets/dist/ui.css') ?>" />
<link rel="stylesheet" href="<?= asset('assets/css/enotf-modals.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/enotf-toast.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/enotf-custom-dropdown.min.css') ?>">
<link rel="stylesheet" href="<?= asset('public/assets/dist/tailwind.css') ?>">
<!-- Bootstrap 5 (Modals, Accordions) — wie v1; KEIN jQuery nötig -->
<script src="<?= asset('public/assets/dist/vendor-enotf.js') ?>"></script>
<!-- Vanilla-UI + v2-Technik -->
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/dialog.js"></script>
<script type="module" src="<?= BASE_PATH ?>assets/js/ui/snackbar.js"></script>
<script type="module" src="<?= asset('plugins/enotf-v2/assets/ev2-select.js') ?>"></script>
<!-- Favicon (v1-Parität) -->
<link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg" />
<link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />
<meta name="theme-color" content="<?= SYSTEM_COLOR ?>" />
<!-- CitizenFX: Session-ID an den FiveM-Client durchreichen (CEF-Embed) -->
<script>
    (function () {
        if (!navigator.userAgent.includes('CitizenFX')) return;
        fetch('<?= BASE_PATH ?>api/character/get-session-id')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.session_id) {
                    var target = (window.parent !== window) ? window.parent : window;
                    target.postMessage({ type: 'ignis_session', session_id: data.session_id }, '*');
                }
            })
            .catch(function () {});
    })();
</script>
<?php require __DIR__ . '/_ev2-select-styles.php'; // Ev2Select/Ev2Suggest im v1-Look (geteilter Block) ?>
