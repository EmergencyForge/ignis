<!DOCTYPE html>
<!--
    eNOTF im Funke-Look: klickbarer Prototyp (nur Mock, keine Server-Logik).
    Liegt in public/enotf-funke/ und lädt die gebauten Funke-Assets von ignis.
    Routing über den Hash, Zustand im localStorage (Präfix "enotf-funke:").
-->
<html lang="de" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <title>eNOTF Funke</title>
    <script>
        /* Thema vor dem ersten Zeichnen setzen, damit nichts aufblitzt. */
        (function () {
            try {
                var t = localStorage.getItem('enotf-funke:theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) { /* private Fenster: Standard bleibt dunkel */ }
        })();
    </script>
    <link rel="preload" href="../assets/fonts/geist/fonts/geist-v4-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="../assets/dist/vendor.css">
    <link rel="stylesheet" href="../assets/fonts/geist/css/all.min.css">
    <link rel="stylesheet" href="../assets/fonts/geist-mono/css/all.min.css">
    <link rel="stylesheet" href="../assets/dist/ui.css">
    <link rel="stylesheet" href="tablet.css">
    <link rel="stylesheet" href="enotf-funke.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon/favicon.svg">
</head>

<body data-ui-skin="core" class="nf-body ignis-tablet">
    <div id="app" class="nf-app" aria-live="off">
        <noscript>
            <p class="nf-noscript">Der Prototyp braucht JavaScript.</p>
        </noscript>
    </div>

    <!-- Funke-Module aus ignis: Akzentrollen und Tooltips, Dialoge, Meldungen -->
    <script type="module" src="../assets/js/ui/preferences.js"></script>
    <script type="module" src="enotf-funke.js"></script>
</body>

</html>
