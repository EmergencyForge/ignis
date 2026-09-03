<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Darstellung: Akzentfarbe der Installation.
 *
 * Die Stylesheets kennen die Farben nur als Tokens (assets/css/_tokens.scss).
 * Der Betreiber legt in der Systemkonfiguration SYSTEM_COLOR fest; head.php
 * legt diesen Wert per accentStyleTag() als <style> über --accent, bevor
 * ein Stylesheet lädt. Solange die Farbe auf einem der Auslieferungswerte
 * steht, bleibt der Tag weg, damit der helle Satz seinen eigenen,
 * dunkleren Akzent behält.
 */
final class Theme
{
    /** Akzent des dunklen Satzes, identisch mit --accent in _tokens.scss. */
    public const DEFAULT_ACCENT = '#f0500a';

    /**
     * Werte, die als „nicht angepasst" gelten: der Token-Standard, das
     * frühere Brand-Orange, das bis zum Redesign fest im CSS stand, und der
     * Seed-Wert der Config-Tabelle (Migration 20250607000053). Bei diesen
     * Werten entscheiden die Tokens, nicht SYSTEM_COLOR.
     */
    private const STOCK_ACCENTS = ['#f0500a', '#ff4d00', '#d10000'];

    /**
     * Die wirksame Akzentfarbe als Hex, z.B. für Farbwerte, die als Daten
     * an den Browser gehen (Kalender-Ereignisse). SYSTEM_COLOR, wenn der
     * Betreiber sie geändert hat, sonst der Token-Standard.
     *
     * @param string|null $configured Testhaken; null liest SYSTEM_COLOR.
     */
    public static function accentHex(?string $configured = null): string
    {
        return self::customAccent($configured) ?? self::DEFAULT_ACCENT;
    }

    /**
     * <style>-Tag, das --accent, --accent-hover und --accent-rgb auf :root
     * setzt. Leer, wenn SYSTEM_COLOR fehlt, ungültig ist oder auf einem
     * Auslieferungswert steht.
     *
     * @param string|null $configured Testhaken; null liest SYSTEM_COLOR.
     */
    public static function accentStyleTag(?string $configured = null): string
    {
        $accent = self::customAccent($configured);
        if ($accent === null) {
            return '';
        }

        [$r, $g, $b] = self::rgb($accent);
        $hover = sprintf('#%02x%02x%02x', (int) round($r * 0.88), (int) round($g * 0.88), (int) round($b * 0.88));

        return '<style id="ignis-accent">:root{--accent:' . $accent . ';--accent-hover:' . $hover
            . ';--accent-rgb:' . $r . ', ' . $g . ', ' . $b . '}</style>';
    }

    private static function customAccent(?string $configured): ?string
    {
        $value = $configured ?? (defined('SYSTEM_COLOR') ? (string) SYSTEM_COLOR : '');
        $value = strtolower(trim($value));

        if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
            return null;
        }

        return in_array($value, self::STOCK_ACCENTS, true) ? null : $value;
    }

    /**
     * @return array{int,int,int}
     */
    private static function rgb(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }
}
