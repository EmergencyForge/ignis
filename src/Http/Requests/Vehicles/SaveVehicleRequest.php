<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/vehicles/vehicles/create und /update:
 * das Fahrzeugformular samt taktischem Zeichen
 * (assets/components/tactical-symbol-form.php).
 *
 * Hier steht nur die Form der Felder. Ob Bezeichnung, Typ und Identifier
 * gefüllt sind, prüft der Controller, weil das Formular dafür seinen
 * eigenen Hinweis (`missing-fields`) samt Rücksprung hat.
 *
 * Die Ausgabe sind die Spalten von `intra_fahrzeuge` plus `id` (0 beim
 * Anlegen). Leere Textfelder werden zu NULL, bis auf die vier Pflichtfelder.
 */
class SaveVehicleRequest extends FormRequest
{
    /** Freitextfelder, die leer als NULL gespeichert werden. */
    private const NULLABLE_TEXT = [
        'allowed_jobs', 'grundzeichen', 'organisation', 'fachaufgabe',
        'einheit', 'symbol', 'typ', 'text', 'tz_name',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',                   v::optional(v::intVal()), false),
            v::key('name',                 v::stringType(), false),
            v::key('kennzeichen',          v::stringType(), false),
            v::key('identifier',           v::stringType(), false),
            v::key('veh_type',             v::stringType(), false),
            v::key('priority',             v::optional(v::intVal()), false),
            v::key('rd_type',              v::optional(v::intVal()), false),
            v::key('stationierung_poi_id', v::optional(v::intVal()), false),
            v::key('active',               v::stringType(), false),
            v::key('allowed_jobs',         v::stringType(), false),
            v::key('grundzeichen',         v::stringType(), false),
            v::key('organisation',         v::stringType(), false),
            v::key('fachaufgabe',          v::stringType(), false),
            v::key('einheit',              v::stringType(), false),
            v::key('symbol',               v::stringType(), false),
            v::key('typ',                  v::stringType(), false),
            v::key('text',                 v::stringType(), false),
            v::key('tz_name',              v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'                   => 'Ungültige ID.',
            'priority'             => 'Die Priorität muss eine Zahl sein.',
            'rd_type'              => 'Ungültiger Typ (Rettungsdienstlich).',
            'stationierung_poi_id' => 'Ungültige Stationierung.',
            'keySet'               => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        $data = [
            'id'          => (int) ($input['id'] ?? 0),
            'name'        => trim((string) ($input['name'] ?? '')),
            'kennzeichen' => trim((string) ($input['kennzeichen'] ?? '')),
            'veh_type'    => trim((string) ($input['veh_type'] ?? '')),
            'identifier'  => trim((string) ($input['identifier'] ?? '')),
            'priority'    => (int) ($input['priority'] ?? 0),
            'rd_type'     => (int) ($input['rd_type'] ?? 0),
            'active'      => isset($input['active']) ? 1 : 0,
            // 0 heißt „keine Wache gewählt". Als NULL ablegen, damit der
            // LEFT JOIN sauber leer bleibt statt auf eine id 0 zu zeigen.
            'stationierung_poi_id' => ((int) ($input['stationierung_poi_id'] ?? 0)) ?: null,
        ];
        foreach (self::NULLABLE_TEXT as $field) {
            $data[$field] = trim((string) ($input[$field] ?? '')) ?: null;
        }

        return $data;
    }
}
