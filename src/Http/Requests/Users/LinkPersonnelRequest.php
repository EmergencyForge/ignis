<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /users/personnel-link.
 *
 * Felder:
 *   - id             (Konto; leer, wenn im Profil keins gewählt ist,
 *                    dann findet der Controller keins)
 *   - action         (link oder unlink)
 *   - mitarbeiter_id (optional; ohne Wahl leer)
 *   - back           (optional, `profile` springt ins Profil zurück)
 */
class LinkPersonnelRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',             v::optional(v::intVal())),
            v::key('action',         v::in(['link', 'unlink'], true)),
            v::key('mitarbeiter_id', v::optional(v::intVal()), false),
            v::key('back',           v::stringType(), false),
        );
    }

    protected static function cast(array $input): array
    {
        return [
            'id'             => (int) $input['id'],
            'action'         => (string) $input['action'],
            'mitarbeiter_id' => (int) ($input['mitarbeiter_id'] ?? 0),
            'back'           => (string) ($input['back'] ?? ''),
        ];
    }
}
