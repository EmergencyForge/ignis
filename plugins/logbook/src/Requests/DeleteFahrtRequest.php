<?php

declare(strict_types=1);

namespace Plugin\Logbook\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /logbook/actions (action=delete). Eine fehlende
 * oder ungültige `id` meldet der Controller.
 */
final class DeleteFahrtRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',        v::optional(v::stringVal()), false),
            // Routing-Felder, nicht validierte Daten
            v::key('action',    v::optional(v::stringVal()), false),
            v::key('return_to', v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int}
     */
    protected static function cast(array $input): array
    {
        return ['id' => (int) ($input['id'] ?? 0)];
    }
}
