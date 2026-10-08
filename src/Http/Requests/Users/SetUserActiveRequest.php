<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /users/toggle-active.
 *
 * Felder:
 *   - id     (Konto)
 *   - action (deactivate oder reactivate)
 */
class SetUserActiveRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',     v::intVal()->positive()),
            v::key('action', v::in(['deactivate', 'reactivate'], true)),
        );
    }

    protected static function cast(array $input): array
    {
        return [
            'id'     => (int) $input['id'],
            'action' => (string) $input['action'],
        ];
    }
}
