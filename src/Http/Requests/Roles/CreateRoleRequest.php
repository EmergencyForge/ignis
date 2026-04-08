<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /benutzer/rollen/create.
 *
 * Felder:
 *   - name        (string, 1-255)
 *   - priority    (int, 0-9999)
 *   - color       (string, einer der Bootstrap-Badge-Farben)
 *   - permissions (optional, Liste von Strings — wird zu [] wenn fehlt)
 */
class CreateRoleRequest extends FormRequest
{
    public const ALLOWED_COLORS = [
        'primary', 'secondary', 'success', 'danger',
        'warning', 'info', 'light', 'dark',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',     v::stringType()->notBlank()->length(1, 255)),
            v::key('priority', v::stringVal()->intVal()->between(0, 9999)),
            v::key('color',    v::in(self::ALLOWED_COLORS, true)),
            v::key('permissions', v::optional(v::arrayType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank'  => 'Bezeichnung darf nicht leer sein.',
            'length'    => 'Bezeichnung muss zwischen {{minValue}} und {{maxValue}} Zeichen lang sein.',
            'intVal'    => 'Priorität muss eine Zahl sein.',
            'between'   => 'Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
            'in'        => 'Ungültige Badge-Farbe.',
            'arrayType' => 'Permissions müssen als Liste übergeben werden.',
        ];
    }

    protected static function cast(array $input): array
    {
        $perms = $input['permissions'] ?? [];
        if (!is_array($perms)) {
            $perms = [];
        }

        return [
            'name'        => trim((string) $input['name']),
            'priority'    => (int) $input['priority'],
            'color'       => (string) $input['color'],
            'permissions' => array_values(array_filter($perms, 'is_string')),
        ];
    }
}
