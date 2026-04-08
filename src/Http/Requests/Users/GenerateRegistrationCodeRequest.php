<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /benutzer/registration-codes (action=generate).
 *
 * Beide Felder sind optional:
 *   - label      (string, max 255) — wird zu null wenn leer
 *   - expires_at (datetime-local Format) — wird zu null wenn leer
 */
class GenerateRegistrationCodeRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        // datetime-local-Inputs sehen aus wie "2026-04-15T13:30". Wir
        // akzeptieren auch "2026-04-15 13:30:00" als Fallback. Respect/Validation
        // hat Probleme mit dem `\T`-Literal in v::date(), daher Regex.
        $datetime = v::regex('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/');

        return v::keySet(
            v::key('label',      v::optional(v::stringType()->length(0, 255)), false),
            v::key('expires_at', v::optional($datetime), false),
            // action ist Teil des POST aber für die Validierung egal:
            v::key('action',     v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'length' => 'Bezeichnung darf maximal {{maxValue}} Zeichen lang sein.',
            'regex'  => 'Ungültiges Datumsformat.',
        ];
    }

    protected static function cast(array $input): array
    {
        $label     = isset($input['label']) ? trim((string) $input['label']) : '';
        $expiresAt = $input['expires_at'] ?? '';

        return [
            'label'      => $label !== '' ? $label : null,
            'expires_at' => $expiresAt !== '' ? (string) $expiresAt : null,
        ];
    }
}
