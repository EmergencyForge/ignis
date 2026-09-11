<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Versorgungsart-Codes der Spalte intra_edivi.transportziel.
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/rettdaten/index.php (Select "transportziel", Z. 215 ff.)
 * - plugins/enotf/templates/enotf/print/index.php ($versorgungsarten, Z. 295 ff.)
 * Beide Quellen sind identisch.
 *
 * ACHTUNG: Dieselbe Spalte wird an anderer Stelle (abschluss/freigabe.php,
 * schnittstelle/index.php) als POI-Identifier interpretiert. Dieser Katalog deckt nur die
 * Versorgungsart-Semantik ab.
 */
final class TransportzielCatalog
{
    /** Code => Label, in der Anzeige-Reihenfolge des Alt-Formulars. */
    public const VERSORGUNGSARTEN = [
        1 => 'ambulante Versorgung vor Ort',
        2 => 'Transport ohne NA (oder mit TNA)',
        21 => 'Transport mit NA (bodengebunden)',
        22 => 'Transport mit NA (RTH)',
        3 => 'Übergabe anderes Rettungsmittel',
        4 => 'Fehleinsatz - kein Patient',
        5 => 'Patient nicht transportfähig',
        6 => 'primäre Todesfeststellung (ohne CPR)',
        99 => 'Sonstige',
    ];

    /** Codes, bei denen ein Transport stattfindet (Billing- und Pflichtfeld-Logik). */
    public const MIT_TRANSPORT = [2, 21, 22];

    public static function label(int $code): ?string
    {
        return self::VERSORGUNGSARTEN[$code] ?? null;
    }
}
