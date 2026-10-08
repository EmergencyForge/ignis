<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /personnel/profile (new=5): Notiz.
 *
 * Leerer Text und unbekannter Typ sind hier noch erlaubt; dann legt der
 * Controller still nichts an.
 *
 * Felder:
 *   - new      (5, wählt die Aktion)
 *   - id       (optional; das Profil steht sonst in `?id=`)
 *   - content  (optional)
 *   - noteType (optional, PersonalLogManager::TYPE_*; fehlt er, gilt -1)
 */
class AddNoteRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('new',      v::stringType(), false),
            v::key('id',       v::optional(v::intVal()), false),
            v::key('content',  v::stringType(), false),
            v::key('noteType', v::intVal(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'       => 'Ungültige ID.',
            'content'  => 'Die Notiz muss Text sein.',
            'noteType' => 'Unbekannter Notiztyp.',
            'keySet'   => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'       => (int) ($input['id'] ?? 0),
            'content'  => trim((string) ($input['content'] ?? '')),
            'noteType' => isset($input['noteType']) ? (int) $input['noteType'] : -1,
        ];
    }
}
