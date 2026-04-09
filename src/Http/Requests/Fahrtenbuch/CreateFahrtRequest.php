<?php

declare(strict_types=1);

namespace App\Http\Requests\Fahrtenbuch;

use App\Http\Requests\FormRequest;
use App\Models\Fahrt;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /fahrtenbuch/actions.php (action=create).
 *
 * Datums-Felder akzeptieren ISO (YYYY-MM-DD) und German (DD.MM.YYYY) —
 * der Cast normalisiert beide auf ISO. Die Multi-Format-Logik bleibt
 * exakt wie im Legacy-Code.
 */
class CreateFahrtRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        $dateRegex = '/^(\d{4}-\d{2}-\d{2}|\d{2}\.\d{2}\.\d{4})$/';
        $timeRegex = '/^\d{2}:\d{2}(:\d{2})?$/';
        $allowedFahrttypen = array_keys(Fahrt::FAHRTTYPEN);

        return v::keySet(
            v::key('datum',              v::stringType()->regex($dateRegex)),
            v::key('abfahrt',            v::stringType()->regex($timeRegex)),
            v::key('ankunft',            v::optional(v::stringType()->regex($timeRegex)), false),
            v::key('vehicle_id',         v::optional(v::stringVal()->intVal()), false),
            v::key('vehicle_identifier', v::stringType()->notBlank()->length(1, 64)),
            v::key('fahrer_name',        v::stringType()->notBlank()->length(1, 255)),
            v::key('fahrttyp',           v::in($allowedFahrttypen, true)),
            v::key('kilometer',          v::optional(v::stringVal()->floatVal()), false),
            v::key('stationierungsort',  v::optional(v::stringType()->length(0, 255)), false),
            v::key('grund',              v::optional(v::stringType()), false),
            v::key('source',             v::optional(v::stringType()->in(['admin', 'enotf', 'firetab'])), false),
            // action + return_to sind Routing-Felder, nicht validierte Daten
            v::key('action',             v::optional(v::stringType()), false),
            v::key('return_to',          v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Pflichtfeld darf nicht leer sein.',
            'regex'    => 'Ungültiges Format.',
            'in'       => 'Ungültiger Wert.',
            'length'   => 'Maximal {{maxValue}} Zeichen.',
            'intVal'   => 'Muss eine Zahl sein.',
            'floatVal' => 'Muss eine Zahl (mit optional Dezimalpunkt) sein.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'datum'              => self::normalizeDate((string) $input['datum']),
            'abfahrt'            => trim((string) $input['abfahrt']),
            'ankunft'            => !empty($input['ankunft']) ? trim((string) $input['ankunft']) : null,
            'vehicle_id'         => !empty($input['vehicle_id']) ? (int) $input['vehicle_id'] : null,
            'vehicle_identifier' => trim((string) $input['vehicle_identifier']),
            'fahrer_name'        => trim((string) $input['fahrer_name']),
            'fahrttyp'           => (string) $input['fahrttyp'],
            'kilometer'          => (isset($input['kilometer']) && $input['kilometer'] !== '') ? (float) $input['kilometer'] : null,
            'stationierungsort'  => isset($input['stationierungsort']) ? trim((string) $input['stationierungsort']) : '',
            'grund'              => !empty($input['grund']) ? trim((string) $input['grund']) : null,
            'source'             => in_array($input['source'] ?? '', ['admin', 'enotf', 'firetab'], true)
                ? $input['source']
                : 'admin',
        ];
    }

    /**
     * Normalisiert DD.MM.YYYY auf YYYY-MM-DD. ISO-Format wird durchgereicht.
     */
    private static function normalizeDate(string $date): string
    {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $m)) {
            return "$m[3]-$m[2]-$m[1]";
        }
        return $date;
    }
}
