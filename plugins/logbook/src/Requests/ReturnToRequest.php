<?php

declare(strict_types=1);

namespace Plugin\Logbook\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Wohin POST /logbook/actions zurückleitet: Admin-Liste, eNOTF oder
 * FireTab.
 *
 * Eigene Klasse ohne `keySet()`, weil der Controller das Ziel schon vor
 * der Prüfung des eigentlichen Formulars braucht: auch eine abgelehnte
 * Fahrt soll auf der Seite landen, von der sie kam.
 */
final class ReturnToRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::key('return_to', v::optional(v::stringVal()), false);
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{return_to:string}
     */
    protected static function cast(array $input): array
    {
        return ['return_to' => (string) ($input['return_to'] ?? 'admin')];
    }
}
