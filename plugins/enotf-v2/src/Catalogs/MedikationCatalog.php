<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Medikamentengabe (Spalte intra_edivi.medis, JSON-Array von
 * {wirkstoff, zeit, applikation, dosierung, einheit, timestamp, …}; Sonderwert '0' = leer).
 *
 * Quellen im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/massnahmen/medikamente/1.php
 *   (Selects "medis-admission" und "medis-unit")
 * - plugins/enotf/templates/enotf/protokoll/massnahmen/medikamente/save_medikament.php (Validierung)
 *
 * Der Wirkstoff-Katalog selbst kommt aus der Tabelle intra_edivi_medikamente und gehört
 * nicht hierher.
 */
final class MedikationCatalog
{
    /** Sonderwert der Spalte medis für "Keine Medikamente". */
    public const KEINE_MEDIKAMENTE = '0';

    /** applikation: gespeicherter Wert => Anzeigelabel (identisch). */
    public const APPLIKATIONSWEGE = [
        'i.v.' => 'i.v.',
        'i.o.' => 'i.o.',
        'i.m.' => 'i.m.',
        's.c.' => 's.c.',
        's.l.' => 's.l.',
        'p.o.' => 'p.o.',
    ];

    /**
     * einheit: gespeicherter Wert => Anzeigelabel.
     * 'mcg' wird im Alt-Formular als "&micro;g" (µg) gerendert; die Eingabe-Parser
     * akzeptieren dort auch µg/μg/ug als Alias für mcg.
     */
    public const EINHEITEN = [
        'mcg' => 'µg',
        'mg' => 'mg',
        'g' => 'g',
        'ml' => 'ml',
        'IE' => 'IE',
    ];

    public static function applikationLabel(string $code): ?string
    {
        return self::APPLIKATIONSWEGE[$code] ?? null;
    }

    public static function einheitLabel(string $code): ?string
    {
        return self::EINHEITEN[$code] ?? null;
    }
}
