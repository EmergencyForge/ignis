<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /benutzer/rollen/update.
 *
 * Wie CreateRoleRequest plus zusätzlich `id` als Pflichtfeld.
 */
class UpdateRoleRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',       v::stringVal()->intVal()->positive()),
            v::key('name',     v::stringType()->notBlank()->length(1, 255)),
            v::key('priority', v::stringVal()->intVal()->between(0, 9999)),
            v::key('color',    v::in(CreateRoleRequest::ALLOWED_COLORS, true)),
            v::key('permissions', v::optional(v::arrayType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'positive'  => 'Ungültige Rollen-ID.',
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
            'id'          => (int) $input['id'],
            'name'        => trim((string) $input['name']),
            'priority'    => (int) $input['priority'],
            'color'       => (string) $input['color'],
            'permissions' => array_values(array_filter($perms, 'is_string')),
        ];
    }
}
