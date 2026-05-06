<?php

declare(strict_types=1);

namespace App\Http\Requests\Enotf;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/enotf/save-fields — der „Universal-Save"-Endpoint.
 *
 * **Minimalistisch.** Nur die drei Input-Felder werden gegen den generischen
 * Shape geprüft:
 *   - `enr` — Einsatznummer, muss existieren (Existenz-Check im Controller)
 *   - `field` — Whitelist-Check passiert weiter unten im Controller gegen
 *     `EnotfController::ALLOWED_FIELDS` (damit es ein Feld bleibt und nicht
 *     hier dupliziert wird)
 *   - `value` — optional, wird pro Feld-Typ separat validiert (Datum, JSON, …)
 *
 * Diese Request wirft bewusst KEINE ValidationException an JsonExceptionMiddleware
 * weiter, weil der Endpoint `text/plain` antwortet — die Fehlermeldungen werden
 * vom Controller via `Response::text('...', 400)` zurückgegeben.
 */
class SaveFieldRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('enr',   v::stringType()->notBlank()->length(1, 64)),
            v::key('field', v::stringType()->notBlank()->length(1, 64)),
            v::key('value', v::optional(v::stringType()), false),
        );
    }

    protected static function cast(array $input): array
    {
        return [
            'enr'   => trim((string) $input['enr']),
            'field' => trim((string) $input['field']),
            'value' => array_key_exists('value', $input) ? $input['value'] : null,
        ];
    }
}
