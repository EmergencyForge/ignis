<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * NACA-Score (Spalten intra_edivi.naca_initial und naca_uebergabe, Werte 0-7).
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/anamnese/2_2.php ($naca_options, volle Labels)
 * - plugins/enotf/templates/enotf/print/index.php ($naca_print_labels, römische Kurzform)
 */
final class NacaCatalog
{
    /** Code => volles Label (Formular anamnese/2_2.php). */
    public const LABELS = [
        0 => 'NACA 0 - Keine Erkrankung/Verletzung',
        1 => 'NACA I - geringfügige Störung',
        2 => 'NACA II - leichte Störung',
        3 => 'NACA III - mäßige Störung',
        4 => 'NACA IV - Lebensgefahr nicht auszuschließen',
        5 => 'NACA V - Akute Lebensgefahr',
        6 => 'NACA VI - Kreislaufstillstand',
        7 => 'NACA VII - Todesfeststellung',
    ];

    /** Code => römische Kurzform (Druckansicht). */
    public const ROEMISCH = [
        0 => '0',
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
    ];

    public static function label(int $code): ?string
    {
        return self::LABELS[$code] ?? null;
    }

    public static function roemisch(int $code): ?string
    {
        return self::ROEMISCH[$code] ?? null;
    }
}
