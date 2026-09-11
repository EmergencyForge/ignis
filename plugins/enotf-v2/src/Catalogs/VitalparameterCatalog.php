<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Catalogs;

/**
 * Vitalparameter des Verlaufs (Tabelle intra_edivi_vitalparameter_einzelwerte).
 *
 * Das Alt-System speichert in parameter_name deutsche Anzeigestrings mit
 * Unicode-Subskript (z. B. "SpO₂"), v2 arbeitet mit Codes.
 * Dieser Katalog mappt in beide Richtungen.
 *
 * Quelle im Alt-System:
 * - plugins/enotf/templates/enotf/protokoll/verlauf/add.php Z. 57 ff.
 *   ($parameter_mapping) und Z. 90 ff. (Bemerkung, parameter_einheit = '').
 *
 * Blutzucker wird immer in mg/dl gespeichert (BloodSugarHelper); die Einheit hier
 * ist die Speichereinheit, nicht die konfigurierbare Anzeige (ENOTF_BZ_UNIT).
 */
final class VitalparameterCatalog
{
    /** Code => ['name' => Legacy-parameter_name, 'einheit' => parameter_einheit]. */
    public const PARAMETER = [
        'spo2' => ['name' => 'SpO₂', 'einheit' => '%'],
        'atemfreq' => ['name' => 'Atemfrequenz', 'einheit' => '/min'],
        'etco2' => ['name' => 'etCO₂', 'einheit' => 'mmHg'],
        'herzfreq' => ['name' => 'Herzfrequenz', 'einheit' => '/min'],
        'rrsys' => ['name' => 'RR systolisch', 'einheit' => 'mmHg'],
        'rrdias' => ['name' => 'RR diastolisch', 'einheit' => 'mmHg'],
        'bz' => ['name' => 'Blutzucker', 'einheit' => 'mg/dl'],
        'temp' => ['name' => 'Temperatur', 'einheit' => '°C'],
        'bemerkung' => ['name' => 'Bemerkung', 'einheit' => ''],
    ];

    /** Legacy-parameter_name eines Codes, null wenn unbekannt. */
    public static function legacyName(string $code): ?string
    {
        return self::PARAMETER[$code]['name'] ?? null;
    }

    /** Speichereinheit eines Codes, null wenn unbekannt. */
    public static function einheit(string $code): ?string
    {
        return self::PARAMETER[$code]['einheit'] ?? null;
    }

    /** Code zu einem Legacy-parameter_name aus der Alt-Tabelle, null wenn unbekannt. */
    public static function fromLegacyName(string $name): ?string
    {
        foreach (self::PARAMETER as $code => $info) {
            if ($info['name'] === $name) {
                return $code;
            }
        }

        return null;
    }
}
