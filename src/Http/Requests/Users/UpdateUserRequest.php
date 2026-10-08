<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /users/edit (mit `new=1`).
 *
 * Gespeichert wird nur die Rolle. Den Benutzernamen und den Knopf
 * `submit` schickt das Formular trotzdem mit, deshalb stehen sie hier.
 *
 * Felder:
 *   - id   (optional; die Seite trägt das Konto auch in `?id=`)
 *   - role (optional; ohne gültige Rolle bleibt alles, wie es ist)
 *   - new, username, submit (werden nicht gelesen)
 */
class UpdateUserRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('new',      v::stringType(), false),
            v::key('id',       v::optional(v::intVal()), false),
            v::key('username', v::stringType(), false),
            v::key('role',     v::optional(v::intVal()), false),
            v::key('submit',   v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'   => 'Ungültiger Benutzer.',
            'role'   => 'Ungültige Rolle.',
            'keySet' => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'   => (int) ($input['id'] ?? 0),
            'role' => (int) ($input['role'] ?? 0),
        ];
    }
}
