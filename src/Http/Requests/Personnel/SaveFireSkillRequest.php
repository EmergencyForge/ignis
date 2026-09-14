<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Feuerwehr-Qualifikation (`intra_mitarbeiter_fwquali`).
 *
 * Wie {@see SaveRankRequest}, mit `shortname` statt `badge` und einem
 * Schalter: `none` markiert den Eintrag, der „keine Qualifikation"
 * bedeutet.
 */
class SaveFireSkillRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('shortname', v::stringType()->notBlank()->length(1, 255)),
            v::key('name',      v::stringType()->notBlank()->length(1, 255)),
            v::key('name_m',    v::stringType()->notBlank()->length(1, 255)),
            v::key('name_w',    v::stringType()->notBlank()->length(1, 255)),
            v::key('priority',  v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('none',      v::optional(v::stringType()), false),
            v::key('id',        v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Kürzel, Bezeichnung und beide Formen dürfen nicht leer sein.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Die Priorität muss eine Zahl sein.',
            'between'  => 'Die Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'        => (int) ($input['id'] ?? 0),
            'shortname' => trim((string) $input['shortname']),
            'name'      => trim((string) $input['name']),
            'name_m'    => trim((string) $input['name_m']),
            'name_w'    => trim((string) $input['name_w']),
            'priority'  => (int) ($input['priority'] ?? 0),
            'none'      => isset($input['none']) ? 1 : 0,
        ];
    }
}
