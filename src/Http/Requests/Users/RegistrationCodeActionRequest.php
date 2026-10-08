<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Welche Aktion ein POST auf /users/registration-codes will.
 *
 * Bewusst ohne `v::keySet()`: hier geht es nur um `action`. Die übrigen
 * Felder prüft danach die Regelmenge der Aktion
 * (GenerateRegistrationCodeRequest, DeleteRegistrationCodeRequest).
 */
class RegistrationCodeActionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::key('action', v::in(['generate', 'delete'], true));
    }

    protected static function messages(): array
    {
        return [
            'action' => 'Unbekannte Aktion.',
        ];
    }

    protected static function cast(array $input): array
    {
        return ['action' => (string) $input['action']];
    }
}
