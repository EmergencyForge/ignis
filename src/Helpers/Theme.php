<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\User;
use App\Session\SessionManager;

/**
 * Darstellung: Akzentfarbe der Installation und Modus des Kontos.
 *
 * Die Stylesheets kennen die Farben nur als Tokens (assets/css/_tokens.scss).
 * Der Betreiber legt in der Systemkonfiguration SYSTEM_COLOR fest; head.php
 * legt diesen Wert per accentStyleTag() als <style> über --accent, bevor
 * ein Stylesheet lädt. Solange die Farbe auf einem der Auslieferungswerte
 * steht, bleibt der Tag weg, damit der helle Satz seinen eigenen,
 * dunkleren Akzent behält.
 *
 * Der Modus (dark, light, system) steht in intra_users.theme und in der
 * Session. headScript() setzt ihn als data-theme am <html>, bevor ein
 * Stylesheet lädt; „system" löst das Script nach der Systemeinstellung
 * des Browsers auf. Die Seiten bauen ihr <html> selbst (die Hülle kommt mit
 * I4), deshalb ein Script statt eines Attributs im Template.
 */
final class Theme
{
    /** Akzent des dunklen Satzes, identisch mit --accent in _tokens.scss. */
    public const DEFAULT_ACCENT = '#f0500a';

    /** Erlaubte Werte für intra_users.theme. */
    public const MODES = ['dark', 'light', 'system'];

    /**
     * Modus des angemeldeten Kontos. Aus der Session; eine Session von vor
     * dieser Spalte liest ihn einmal aus intra_users und merkt ihn sich.
     * Ohne Login: dark.
     */
    public static function mode(): string
    {
        $mode = $_SESSION['theme'] ?? null;
        if (is_string($mode) && in_array($mode, self::MODES, true)) {
            return $mode;
        }

        $userId = SessionManager::userId();
        if ($userId === null) {
            return 'dark';
        }

        try {
            $stored = User::query()->whereKey($userId)->value('theme');
        } catch (\Throwable) {
            return 'dark';
        }

        $mode = is_string($stored) && in_array($stored, self::MODES, true) ? $stored : 'dark';
        $_SESSION['theme'] = $mode;

        return $mode;
    }

    /**
     * Inline-Script für den <head> von Seiten, die ihr <html> selbst bauen
     * (eNOTF, fireTab, Login): setzt data-theme am <html> vor dem ersten
     * Stylesheet, damit die Tokens des richtigen Satzes gelten, bevor
     * etwas gezeichnet wird. Die Hülle (templates/layouts/admin.php)
     * schreibt den Modus als Attribut und braucht nur systemScript().
     */
    public static function headScript(): string
    {
        $mode = self::mode();
        if ($mode === 'system') {
            return self::systemScript();
        }

        return '<script>document.documentElement.dataset.theme = "' . $mode . '";</script>';
    }

    /**
     * Löst „system" nach der Systemeinstellung des Browsers auf; leer für
     * dark und light, die als Attribut am <html> stehen.
     */
    public static function systemScript(): string
    {
        if (self::mode() !== 'system') {
            return '';
        }

        return "<script>document.documentElement.dataset.theme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';</script>";
    }

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
