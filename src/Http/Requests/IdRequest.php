<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Post, der einen Datensatz über `id` anspricht: Löschen, Umschalten,
 * Ausführen.
 *
 * Bewusst ohne `v::keySet()`. Hier zählt nur die Kennung, und der alte
 * Profil-Post holt sie sich so aus einem Formular mit vielen anderen
 * Feldern, bevor er dessen Regeln prüft.
 */
final class IdRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::key('id', v::intVal()->positive());
    }

    protected static function messages(): array
    {
        return [
            'id' => 'Ungültige ID.',
        ];
    }

    protected static function cast(array $input): array
    {
        return ['id' => (int) $input['id']];
    }
}
