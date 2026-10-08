<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /inbox/read.
 *
 * Felder:
 *   - id     (optional, Zahl): ohne oder mit 0 gelten alle Einträge
 *   - return (optional): Rücksprung; ob er taugt, prüft der Controller
 */
class MarkInboxReadRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',     v::optional(v::intVal()), false),
            v::key('return', v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'     => 'Ungültige ID.',
            'return' => 'Ungültiger Rücksprung.',
            'keySet' => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);

        return [
            'id'     => $id > 0 ? $id : null,
            'return' => (string) ($input['return'] ?? ''),
        ];
    }
}
