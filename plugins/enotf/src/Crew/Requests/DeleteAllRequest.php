<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * „Alle löschen" in der Übersicht (POST /enotf/overview). Ohne
 * `delete_all` passiert nichts.
 */
final class DeleteAllRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        // new Key statt v::key(): die PHPStan-Ausnahme für v::key() in
        // keySet() gilt nur unter plugins/*/src/Requests.
        return v::keySet(
            new Key('delete_all', v::optional(v::stringVal()), false),
            new Key('_csrf',      v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{delete_all:bool}
     */
    protected static function cast(array $input): array
    {
        return ['delete_all' => isset($input['delete_all'])];
    }
}
