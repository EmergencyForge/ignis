<?php

declare(strict_types=1);

namespace App\Http\Requests\Mitarbeiter;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /mitarbeiter/create.php (AJAX-Endpoint).
 *
 * Felder:
 *   - fullname    (string, 1-255)
 *   - gebdatum    (date YYYY-MM-DD)
 *   - dienstgrad  (int, positive)
 *   - geschlecht  (0|1|2)
 *   - discordtag  (17-20 Ziffern)
 *   - telefonnr   (optional)
 *   - dienstnr    (regex: mind. eine Zahl, A-Z 0-9 -)
 *   - einstdatum  (date)
 *   - charakterid (CHAR_ID-Konstante: required wenn aktiv, sonst optional)
 *
 * Die `charakterid`-Bedingung ist im Controller nach `validate()` extra,
 * weil Respect/Validation conditional rules nicht so elegant kann.
 */
class CreateMitarbeiterRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        $dienstnrPattern = '/^(?=.*[0-9])[A-Za-z0-9\-]+$/';
        $dateRegex       = '/^\d{4}-\d{2}-\d{2}$/';

        return v::keySet(
            v::key('fullname',    v::stringType()->notBlank()->length(1, 255)),
            v::key('gebdatum',    v::stringType()->regex($dateRegex)),
            v::key('dienstgrad',  v::stringVal()->intVal()->positive()),
            v::key('geschlecht',  v::stringVal()->intVal()->in(['0', '1', '2'])),
            v::key('discordtag',  v::stringType()->regex('/^[0-9]{17,20}$/')),
            v::key('telefonnr',   v::optional(v::stringType()->length(0, 50)), false),
            v::key('dienstnr',    v::stringType()->notBlank()->regex($dienstnrPattern)),
            v::key('einstdatum',  v::stringType()->regex($dateRegex)),
            v::key('charakterid', v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Pflichtfeld darf nicht leer sein.',
            'regex'    => 'Ungültiges Format.',
            'intVal'   => 'Muss eine Zahl sein.',
            'positive' => 'Muss positiv sein.',
            'in'       => 'Ungültiger Wert.',
            'length'   => 'Maximal {{maxValue}} Zeichen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'fullname'    => trim((string) $input['fullname']),
            'gebdatum'    => (string) $input['gebdatum'],
            'dienstgrad'  => (int) $input['dienstgrad'],
            'geschlecht'  => (int) $input['geschlecht'],
            'discordtag'  => (string) $input['discordtag'],
            'telefonnr'   => isset($input['telefonnr']) ? trim((string) $input['telefonnr']) : '',
            'dienstnr'    => trim((string) $input['dienstnr']),
            'einstdatum'  => (string) $input['einstdatum'],
            'charakterid' => isset($input['charakterid']) ? trim((string) $input['charakterid']) : '',
        ];
    }
}
