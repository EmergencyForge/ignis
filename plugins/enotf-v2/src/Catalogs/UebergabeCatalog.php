<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Übergabe-Codes (intra_edivi.uebergabe_ort, intra_edivi.uebergabe_an).
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/abschluss/3_1.php (Radio uebergabe_ort)
 * - plugins/enotf/templates/enotf/protokoll/abschluss/3_2.php (Radio uebergabe_an)
 * - plugins/enotf/templates/enotf/print/index.php ($uebergabe_ort_labels / $uebergabe_an_labels, Z. 2250 ff.)
 *
 * Abweichung: Für uebergabe_ort=10 zeigt das Formular "Herzkatheterlabor", der Druck "HKL".
 * Hier gilt das Formular-Label; die Druck-Kurzform steht in ORT_KURZLABELS.
 */
final class UebergabeCatalog
{
    /** uebergabe_ort: Code => Label (Formular abschluss/3_1.php). */
    public const ORT = [
        1 => 'Schockraum',
        2 => 'Praxis',
        3 => 'ZNA / INA',
        4 => 'Stroke Unit',
        5 => 'Intensivstation',
        6 => 'OP direkt',
        7 => 'Hausarzt',
        8 => 'Fachambulanz',
        9 => 'Chest Pain Unit',
        10 => 'Herzkatheterlabor',
        11 => 'Allgemeinstation',
        12 => 'Einsatzstelle',
        99 => 'Sonstige',
    ];

    /** Abweichende Kurzformen aus print/index.php (nur wo der Druck vom Formular abweicht). */
    public const ORT_KURZLABELS = [
        10 => 'HKL',
    ];

    /** uebergabe_an: Code => Label (Formular abschluss/3_2.php, identisch mit Druck). */
    public const AN = [
        1 => 'Arzt',
        2 => 'Pflegepersonal',
        3 => 'Rettungssanitäter',
        4 => 'Rettungsassistent',
        5 => 'Notfallsanitäter',
        6 => 'Polizei',
        7 => 'Angehörige',
        99 => 'Sonstige',
    ];

    public static function ortLabel(int $code): ?string
    {
        return self::ORT[$code] ?? null;
    }

    public static function anLabel(int $code): ?string
    {
        return self::AN[$code] ?? null;
    }
}
