<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Einsatzbezogene Code-Listen: Einsatzart (eart), Einsatzort-Typ (elokation)
 * und Einsatz-Besonderheiten (ebesonderheiten als JSON-Array von int).
 *
 * Quellen im Alt-System:
 * - eart: plugins/enotf/templates/enotf/protokoll/rettdaten/index.php (Select, Z. 230 ff.)
 * - elokation: plugins/enotf/templates/enotf/protokoll/anamnese/3.php (Radio)
 * - ebesonderheiten: plugins/enotf/templates/enotf/protokoll/abschluss/1.php (Checkboxen)
 */
final class EinsatzCatalog
{
    /** eart: Code => Label. */
    public const EART = [
        1 => 'Notfallrettung - Primäreinsatz mit NA',
        11 => 'Notfallrettung - Primäreinsatz ohne NA',
        2 => 'Notfallrettung - Verlegung mit NA',
        21 => 'Notfallrettung - Verlegung ohne NA',
        3 => 'Intensivtransport',
        4 => 'Krankentransport',
    ];

    /** elokation: Code => Label. 98 = Sonstige, 99 = nicht dokumentiert. */
    public const ELOKATION = [
        1 => 'Wohnung',
        2 => 'Arbeitsplatz',
        3 => 'Altenheim',
        4 => 'öffentlicher Raum',
        5 => 'Arztpraxis',
        6 => 'Straße',
        7 => 'Krankenhaus',
        8 => 'Massenveranstaltung',
        9 => 'Bildungseinrichtung',
        10 => 'Sportstätte',
        11 => 'Geburtshaus/-einrichtung',
        98 => 'Sonstige',
        99 => 'nicht dokumentiert',
    ];

    /**
     * ebesonderheiten: Code => Label (Mehrfachauswahl als JSON-Array von int).
     * Code 1 ("keine") ist im Alt-Frontend exklusiv zu allen anderen.
     */
    public const EBESONDERHEITEN = [
        1 => 'keine',
        2 => 'nächste geeignete Klinik nicht aufnahmebereit',
        3 => 'Patient lehnt indizierte Maßnahmen ab',
        4 => 'bewusster Therapieverzicht',
        5 => 'Patient lehnt Transport ab',
        6 => 'Vorsorgliche Bereitstellung',
        7 => 'Zwangsunterbringung',
        8 => 'aufwändige technische Rettung',
        9 => 'Einsatz mit LNA/OrgL',
        10 => 'mehrere Patienten',
        11 => 'MANV',
        12 => 'kein Notarzt in angemessener Zeit verfügbar',
        13 => 'erschwerter Patientenzugang',
        14 => 'verzögerte Patientenübergabe',
        99 => 'Sonstige',
    ];

    /** ebesonderheiten-Code, der alle anderen ausschließt. */
    public const EBESONDERHEITEN_EXKLUSIV = 1;

    public static function eartLabel(int $code): ?string
    {
        return self::EART[$code] ?? null;
    }

    public static function elokationLabel(int $code): ?string
    {
        return self::ELOKATION[$code] ?? null;
    }

    public static function besonderheitLabel(int $code): ?string
    {
        return self::EBESONDERHEITEN[$code] ?? null;
    }
}
