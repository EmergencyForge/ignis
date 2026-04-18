<?php

/**
 * Tailwind-Preview-Seite — zeigt, dass der Build funktioniert und
 * die Design-Tokens korrekt aus tailwind.config.js gezogen werden.
 * Kein Login-Gate, weil's eine reine UI-Demo ist.
 */

declare(strict_types=1);

$distPath = __DIR__ . '/assets/dist/tailwind.css';
$cssExists = is_file($distPath);
$cssUrl    = '/public/assets/dist/tailwind.css';
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>intraRP — Tailwind-Preview</title>
    <?php if ($cssExists): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl) ?>">
    <?php else: ?>
        <style>body{font-family:system-ui;background:#222;color:#fff;padding:2rem;} .err{color:#ff4d00;}</style>
    <?php endif; ?>
</head>
<body class="bg-surface-deep text-white font-sans min-h-screen p-8">
    <?php if (!$cssExists): ?>
        <p class="err">⚠ tailwind.css fehlt. Bitte `npm run build` ausführen.</p>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto space-y-8">
        <header>
            <h1 class="font-heading font-bold text-4xl text-brand">intraRP Tailwind</h1>
            <p class="text-md text-white/70 mt-2">Design-Tokens-Preview aus <code class="font-mono text-xs bg-surface px-1.5 py-0.5 rounded-sm">tailwind.config.js</code></p>
        </header>

        <section class="bg-surface rounded-lg p-6 shadow-medium">
            <h2 class="font-heading font-semibold text-xl mb-4">Brand-Farben</h2>
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-brand rounded-md p-4 text-center">#FF4D00 <br><span class="text-xs opacity-75">brand</span></div>
                <div class="bg-brand-light rounded-md p-4 text-center">#FF6A33 <br><span class="text-xs opacity-75">brand-light</span></div>
                <div class="bg-brand-dark rounded-md p-4 text-center">#CC3D00 <br><span class="text-xs opacity-75">brand-dark</span></div>
            </div>
        </section>

        <section class="bg-surface rounded-lg p-6 shadow-medium">
            <h2 class="font-heading font-semibold text-xl mb-4">Dark-Palette</h2>
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-surface-deep rounded-md p-4 text-center border border-white/10">#232128 <br><span class="text-xs opacity-75">surface-deep</span></div>
                <div class="bg-surface rounded-md p-4 text-center border border-white/10">#2b2930 <br><span class="text-xs opacity-75">surface</span></div>
                <div class="bg-surface-soft rounded-md p-4 text-center border border-white/10">#37343e <br><span class="text-xs opacity-75">surface-soft</span></div>
            </div>
        </section>

        <section class="bg-surface rounded-lg p-6 shadow-medium">
            <h2 class="font-heading font-semibold text-xl mb-4">Typografie</h2>
            <div class="space-y-3">
                <p class="font-heading text-2xl">Poppins — font-heading</p>
                <p class="font-sans text-md">Rubik — font-sans (Default-Body)</p>
                <p class="font-display text-md">Maven Pro — font-display</p>
                <p class="font-ui text-md">PT Sans — font-ui</p>
                <p class="font-mono text-sm bg-surface-deep px-2 py-1 rounded-sm inline-block">Inconsolata — font-mono</p>
            </div>
        </section>

        <section class="bg-surface rounded-lg p-6 shadow-medium">
            <h2 class="font-heading font-semibold text-xl mb-4">Text-Größen (Admin-Skala)</h2>
            <ul class="space-y-1">
                <li class="text-xxs">0.72rem — text-xxs</li>
                <li class="text-xs">0.78rem — text-xs</li>
                <li class="text-sm">0.82rem — text-sm</li>
                <li class="text-md">0.88rem — text-md</li>
            </ul>
        </section>

        <section class="bg-surface rounded-lg p-6 shadow-medium">
            <h2 class="font-heading font-semibold text-xl mb-4">Shadows &amp; Radius</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-surface-deep rounded-sm p-4 shadow-soft text-center">rounded-sm<br>shadow-soft</div>
                <div class="bg-surface-deep rounded-md p-4 shadow-medium text-center">rounded-md<br>shadow-medium</div>
                <div class="bg-surface-deep rounded-xl p-4 shadow-strong text-center">rounded-xl<br>shadow-strong</div>
            </div>
        </section>

        <footer class="text-xs text-white/50 pt-6">
            Preview wird nach der Template-Migration entfernt.
        </footer>
    </div>
</body>
</html>
