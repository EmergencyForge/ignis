<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Rettungsdienst-Qualifikation (`intra_mitarbeiter_rdquali`).
 *
 * Wie {@see SaveRankRequest}, mit zwei Schaltern: `none` markiert den
 * Eintrag für „keine Qualifikation", `trainable` den, auf den ausgebildet
 * werden kann. Die Abkürzung ist optional und hat nur fünfzig Zeichen —
 * die einzige Spalte dieser vier Kataloge, die kürzer ist als 255.
 */
class SaveMedicSkillRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',       v::stringType()->notBlank()->length(1, 255)),
            v::key('name_m',     v::stringType()->notBlank()->length(1, 255)),
            v::key('name_w',     v::stringType()->notBlank()->length(1, 255)),
            v::key('abkuerzung', v::optional(v::stringType()->length(0, 50)), false),
            v::key('priority',   v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('none',       v::optional(v::stringType()), false),
            v::key('trainable',  v::optional(v::stringType()), false),
            v::key('id',         v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Bezeichnung, männliche und weibliche Form dürfen nicht leer sein.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Die Priorität muss eine Zahl sein.',
            'between'  => 'Die Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        $kurz = trim((string) ($input['abkuerzung'] ?? ''));

        return [
            'id'         => (int) ($input['id'] ?? 0),
            'name'       => trim((string) $input['name']),
            'name_m'     => trim((string) $input['name_m']),
            'name_w'     => trim((string) $input['name_w']),
            'abkuerzung' => $kurz === '' ? null : $kurz,
            'priority'   => (int) ($input['priority'] ?? 0),
            'none'       => isset($input['none']) ? 1 : 0,
            'trainable'  => isset($input['trainable']) ? 1 : 0,
        ];
    }
}
