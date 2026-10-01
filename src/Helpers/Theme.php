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

    /** Session-Schlüssel der Einmalmarke für die Theme-Überblendung. */
    private const TRANSITION_KEY = 'theme_transition';

    /** Merkt vor, dass der nächste Seitenaufruf den Theme-Wechsel überblendet. */
    public static function flagTransition(): void
    {
        $_SESSION[self::TRANSITION_KEY] = true;
    }

    /** Liefert die Marke einmal und löscht sie dabei. */
    public static function consumeTransition(): bool
    {
        $flag = ! empty($_SESSION[self::TRANSITION_KEY]);
        unset($_SESSION[self::TRANSITION_KEY]);
        return $flag;
    }

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

    /**
     * Ob die angepasste Akzentfarbe so rot ist, dass Primärknopf und
     * Fortschritt wie Gefahr aussehen. Gefahr liegt in oklch bei H 22, das
     * Orange von ignis bei H 38. Ein Farbton bis 12° um H 22 gilt als rot,
     * ein fast graues Rot (Chroma unter 0,1) nicht.
     *
     * @param string|null $configured Testhaken; null liest SYSTEM_COLOR.
     */
    public static function accentLooksLikeDanger(?string $configured = null): bool
    {
        $accent = self::customAccent($configured);
        if ($accent === null) {
            return false;
        }

        [$chroma, $hue] = self::chromaAndHue(self::rgb($accent));

        return $chroma >= 0.1 && abs($hue - 22) <= 12;
    }

    /**
     * Chroma und Farbton (Grad) in oklch, nach Björn Ottosson.
     *
     * @param array{int,int,int} $rgb
     * @return array{float,float}
     */
    private static function chromaAndHue(array $rgb): array
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $a  = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $hue = rad2deg(atan2($bb, $a));

        return [sqrt($a * $a + $bb * $bb), $hue < 0 ? $hue + 360 : $hue];
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
