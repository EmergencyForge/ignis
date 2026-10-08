<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Protokoll anlegen (POST /enotf/create).
 *
 * Das Format der ENR prüft der Controller, er leitet bei einer falschen
 * mit `error=invalid_enr` aufs Formular zurück. `rdprot` und `naprot` sind
 * die beiden Absendeknöpfe, die Protokollart steht in `prot_by`.
 */
final class CreateProtocolRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        // new Key statt v::key(): die PHPStan-Ausnahme für v::key() in
        // keySet() gilt nur unter plugins/*/src/Requests.
        return v::keySet(
            new Key('enr',          v::optional(v::stringVal()), false),
            new Key('prot_by',      v::optional(v::stringVal()), false),
            new Key('force_create', v::optional(v::stringVal()), false),
            new Key('rdprot',       v::optional(v::stringVal()), false),
            new Key('naprot',       v::optional(v::stringVal()), false),
            new Key('_csrf',        v::optional(v::stringVal()), false),
        );
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{enr:string, prot_by:int, force_create:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'enr'          => is_string($input['enr'] ?? null) ? trim($input['enr']) : '',
            'prot_by'      => (int) ($input['prot_by'] ?? 0),
            'force_create' => (int) ($input['force_create'] ?? 0),
        ];
    }
}
