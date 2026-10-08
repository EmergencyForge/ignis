<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /personnel/profile (new=4): Fachdienste.
 *
 * Felder:
 *   - new         (4, wählt die Aktion)
 *   - id          (optional; das Profil steht sonst in `?id=`)
 *   - fachdienste (optional, Liste der Nummern; ohne Häkchen fehlt sie)
 */
class UpdateFachdiensteRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('new',         v::stringType(), false),
            v::key('id',          v::optional(v::intVal()), false),
            v::key('fachdienste', v::arrayType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'          => 'Ungültige ID.',
            'fachdienste' => 'Die Fachdienste müssen als Liste kommen.',
            'keySet'      => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'          => (int) ($input['id'] ?? 0),
            'fachdienste' => array_values(array_filter((array) ($input['fachdienste'] ?? []), 'is_string')),
        ];
    }
}
