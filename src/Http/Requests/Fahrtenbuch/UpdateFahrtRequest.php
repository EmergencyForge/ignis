<?php

declare(strict_types=1);

namespace App\Http\Requests\Fahrtenbuch;

use App\Http\Requests\FormRequest;
use App\Models\Fahrt;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /fahrtenbuch/actions.php (action=update).
 *
 * Wie CreateFahrtRequest, aber mit ID-Pflicht und ohne vehicle_identifier-
 * Pflicht (wird im Controller aus dem bestehenden Eintrag übernommen, falls
 * fehlt).
 */
class UpdateFahrtRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        $dateRegex = '/^(\d{4}-\d{2}-\d{2}|\d{2}\.\d{2}\.\d{4})$/';
        $timeRegex = '/^\d{2}:\d{2}(:\d{2})?$/';
        $allowedFahrttypen = array_keys(Fahrt::FAHRTTYPEN);

        return v::keySet(
            v::key('id',                 v::stringVal()->intVal()->positive()),
            v::key('datum',              v::stringType()->regex($dateRegex)),
            v::key('abfahrt',            v::stringType()->regex($timeRegex)),
            v::key('ankunft',            v::optional(v::stringType()->regex($timeRegex)), false),
            v::key('vehicle_id',         v::optional(v::stringVal()->intVal()), false),
            v::key('vehicle_identifier', v::optional(v::stringType()->length(0, 64)), false),
            v::key('fahrer_name',        v::optional(v::stringType()->length(0, 255)), false),
            v::key('fahrttyp',           v::in($allowedFahrttypen, true)),
            v::key('kilometer',          v::optional(v::stringVal()->floatVal()), false),
            v::key('stationierungsort',  v::optional(v::stringType()->length(0, 255)), false),
            v::key('grund',              v::optional(v::stringType()), false),
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
            'positive' => 'Ungültige ID.',
            'length'   => 'Maximal {{maxValue}} Zeichen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'                 => (int) $input['id'],
            'datum'              => self::normalizeDate((string) $input['datum']),
            'abfahrt'            => trim((string) $input['abfahrt']),
            'ankunft'            => !empty($input['ankunft']) ? trim((string) $input['ankunft']) : null,
            'vehicle_id'         => !empty($input['vehicle_id']) ? (int) $input['vehicle_id'] : null,
            'vehicle_identifier' => isset($input['vehicle_identifier']) ? trim((string) $input['vehicle_identifier']) : '',
            'fahrer_name'        => isset($input['fahrer_name']) ? trim((string) $input['fahrer_name']) : '',
            'fahrttyp'           => (string) $input['fahrttyp'],
            'kilometer'          => (isset($input['kilometer']) && $input['kilometer'] !== '') ? (float) $input['kilometer'] : null,
            'stationierungsort'  => isset($input['stationierungsort']) ? trim((string) $input['stationierungsort']) : '',
            'grund'              => !empty($input['grund']) ? trim((string) $input['grund']) : null,
        ];
    }

    private static function normalizeDate(string $date): string
    {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $m)) {
            return "$m[3]-$m[2]-$m[1]";
        }
        return $date;
    }
}
