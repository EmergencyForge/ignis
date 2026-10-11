<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests\Asu;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * POST /api/fire/asu/trupp: Trupp anlegen (ohne trupp_id) oder ändern.
 * Mitglieder kommen als Liste mit Position; leerer Name heißt „Platz frei“.
 */
class AsuTruppRequest extends FormRequest
{
    public const POSITIONS = ['tf', 'tm1', 'tm2'];
    public const ROLES     = ['angriff', 'sicherheit'];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('incident_id', v::intVal()->positive()),
            v::key('trupp_id', v::optional(v::intVal()->positive()), false),
            v::key('name', v::stringType()->notBlank()->length(1, 50)),
            v::key('role', v::in(self::ROLES)),
            v::key('task', v::optional(v::stringType()->length(0, 255)), false),
            v::key('expected_minutes', v::intVal()->between(5, 120)),
            v::key('members', v::arrayType()->length(1, 3)->each(v::keySet(
                v::key('position', v::in(self::POSITIONS)),
                v::key('name', v::stringType()->length(0, 100)),
            ))),
        );
    }

    protected static function messages(): array
    {
        return [
            'incident_id'      => 'Einsatz fehlt.',
            'name'             => 'Der Trupp braucht einen Namen oder Funkrufnamen (höchstens 50 Zeichen).',
            'role'             => 'Unbekannte Trupp-Art.',
            'expected_minutes' => 'Die erwartete Einsatzzeit muss zwischen 5 und 120 Minuten liegen.',
            'members'          => 'Ein Trupp hat ein bis drei Plätze: Truppführer, Truppmann, Truppmann 2.',
        ];
    }

    protected static function cast(array $input): array
    {
        $members = [];
        foreach ($input['members'] as $m) {
            $members[$m['position']] = trim((string) $m['name']);
        }

        return [
            'incident_id'      => (int) $input['incident_id'],
            'trupp_id'         => isset($input['trupp_id']) ? (int) $input['trupp_id'] : null,
            'name'             => trim((string) $input['name']),
            'role'             => (string) $input['role'],
            'task'             => trim((string) ($input['task'] ?? '')),
            'expected_minutes' => (int) $input['expected_minutes'],
            'members'          => $members,
        ];
    }
}
