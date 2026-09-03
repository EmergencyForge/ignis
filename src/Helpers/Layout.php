<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Legt eine gerenderte Ansicht in eine Seitenhülle aus templates/layouts/.
 *
 * Controller::renderView() ruft das für jedes Template auf, das `$layout`
 * setzt; die Root-Skripte (index.php, dashboard.php) rufen es selbst mit
 * ihrem gepufferten Inhalt. Das Layout läuft im Scope von render() und
 * sieht darum nur die Variablen von hier:
 *
 *   $layoutContent  gerenderter Inhalt, landet im <main>
 *   $layoutBodyId   id des <body>
 *   $layoutBodyPage data-page des <body> (Standard: die id)
 *   $layoutHead     zusätzliches Markup für den <head>
 *   $SITE_TITLE     Seitentitel, gelesen von head.php
 */
final class Layout
{
    public static function path(string $name): string
    {
        return dirname(__DIR__, 2) . '/templates/layouts/' . $name . '.php';
    }

    /**
     * @param array<string, mixed> $vars  SITE_TITLE, bodyId, bodyPage, layoutHead
     */
    public static function render(string $name, string $content, array $vars = []): string
    {
        $layoutPath = self::path($name);
        if (!is_file($layoutPath)) {
            throw new \RuntimeException("Layout not found: $name ($layoutPath)");
        }

        $layoutContent  = $content;
        $layoutBodyId   = self::text($vars['bodyId'] ?? null) ?? 'app';
        $layoutBodyPage = self::text($vars['bodyPage'] ?? null) ?? $layoutBodyId;
        $layoutHead     = self::text($vars['layoutHead'] ?? null) ?? '';

        $siteTitle = self::text($vars['SITE_TITLE'] ?? null);
        if ($siteTitle !== null) {
            $SITE_TITLE = $siteTitle;
        }

        ob_start();
        try {
            require $layoutPath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
