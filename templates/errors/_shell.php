<?php

/**
 * Gemeinsame Huelle der Fehlerseiten (404, 403).
 *
 * Standalone, ohne Navigation und Seitenleiste: sie muss auch ohne aktive
 * Sitzung tragen, etwa wenn jemand ohne Anmeldung eine geschuetzte URL
 * direkt aufruft. Deshalb kein head.php (das braucht Konfiguration und
 * Sitzung), die Stylesheets und Module stehen hier selbst. Markup und
 * Aussehen: Fehlerseite im UI-Paket (auth() mit error-stage()).
 *
 * Erwartete Variablen:
 *   @var string      $errTitle      Titel im Browser-Tab
 *   @var string      $errCode       '404' oder '403', die Ziffern der Bühne
 *   @var string      $errHeadline   Ueberschrift
 *   @var string      $errText       Ein Satz dazu, was passiert ist
 *   @var string|null $errPath       Aufgerufene Adresse (nur 404), schon gekürzt
 *   @var string      $errBackUrl    Ziel des ersten Knopfes
 *   @var string      $errBackLabel  Beschriftung dieses Knopfes
 *   @var string      $errBackIcon   Font-Awesome-Klasse dieses Knopfes
 *   @var string      $errPage       Wert fuer data-page
 */

// Der Bootstrap wird versucht, aber nicht vorausgesetzt: eine Fehlerseite
// muss gerade dann tragen, wenn die Konfiguration nicht steht.
if (!defined('BASE_PATH') && is_file(dirname(__DIR__, 2) . '/assets/config/config.php')) {
    try {
        require_once dirname(__DIR__, 2) . '/assets/config/config.php';
    } catch (\Throwable) {
        // Ohne Konfiguration bleibt der Pfad-Rückfall unten.
    }
}

$errBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= \App\Helpers\Theme::headScript() ?>
    <title><?= htmlspecialchars($errTitle) ?></title>
    <link rel="preload" href="<?= $errBase ?>assets/fonts/geist/fonts/geist-v4-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('public/assets/dist/vendor.css') ?>">
    <link rel="stylesheet" href="<?= $errBase ?>assets/fonts/geist/css/all.min.css">
    <link rel="stylesheet" href="<?= $errBase ?>assets/fonts/geist-mono/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('public/assets/dist/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('public/assets/dist/ui.css') ?>">
    <?php // Was auf der Anmeldung legacy-utilities.css und body#alogin setzen. ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; }
        button { font: inherit; }
    </style>
    <?= \App\Helpers\Theme::accentStyleTag() ?>
    <?php // Akzentrollen aus SYSTEM_COLOR wie auf der Anmeldung. ?>
    <script type="module" src="<?= $errBase ?>assets/js/ui/preferences.js"></script>
    <script type="module" src="<?= $errBase ?>assets/js/ui/error-stage.js"></script>
    <link rel="icon" type="image/svg+xml" href="<?= $errBase ?>assets/favicon/favicon.svg">
    <link rel="shortcut icon" href="<?= $errBase ?>assets/favicon/favicon.ico">
</head>

<body data-ui-skin="core" data-page="<?= htmlspecialchars($errPage) ?>">
    <div class="twplus-login twplus-login--page">
        <aside class="twplus-login__visual" aria-hidden="true">
            <div class="ignis-login-stage">
                <div class="ignis-login-stage__heat"></div>
            </div>
        </aside>

        <?php require dirname(__DIR__) . '/partials/login-brand.php'; ?>

        <div class="ignis-error-stage" aria-hidden="true">
            <div class="ignis-error-stage__code">
                <div class="ignis-error-stage__ember"></div>
                <?php foreach (str_split($errCode) as $errDigit): ?>
                    <span class="ignis-error-stage__digit" data-digit="<?= htmlspecialchars($errDigit) ?>"></span>
                <?php endforeach; ?>
            </div>
        </div>

        <main class="twplus-login__content twplus-login__content--bare" aria-labelledby="error-title">
            <div class="twplus-login__well">
                <h1 class="twplus-login__title" id="error-title"><span class="ignis-sr-only">Fehler <?= htmlspecialchars($errCode) ?>: </span><?= htmlspecialchars($errHeadline) ?></h1>
                <p class="twplus-login__lead"><?= htmlspecialchars($errText) ?></p>
                <?php if ($errPath !== null): ?>
                    <p class="twplus-login__path"><span>Adresse</span><code><?= htmlspecialchars($errPath) ?></code></p>
                <?php endif; ?>
                <div class="twplus-login__actions twplus-login__actions--row">
                    <a class="ignis-btn ignis-btn--primary ignis-btn--lg" href="<?= htmlspecialchars($errBackUrl) ?>"><i class="fa-solid <?= $errBackIcon ?>" aria-hidden="true"></i> <?= htmlspecialchars($errBackLabel) ?></a>
                    <button class="ignis-btn ignis-btn--secondary ignis-btn--lg" type="button" data-ignis-history-back hidden><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Vorherige Seite</button>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
