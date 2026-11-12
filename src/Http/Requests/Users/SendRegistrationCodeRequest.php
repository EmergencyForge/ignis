<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /users/registration-codes (action=send): die
 * Einladung, die der Discord-Bot zustellen soll.
 */
class SendRegistrationCodeRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('action',  v::in(['send'], true)),
            v::key('code_id', v::intVal()->positive()),
        );
    }

    protected static function cast(array $input): array
    {
        return ['code_id' => (int) $input['code_id']];
    }
}
