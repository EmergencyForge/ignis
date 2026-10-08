<?php

declare(strict_types=1);

namespace Plugin\Forms\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Knöpfe, die nur Kennungen schicken: Antragstyp aktivieren oder löschen
 * (`id`), Feld löschen (`id` des Feldes und `antragstyp_id`). Ob die
 * Kennungen zu etwas Vorhandenem gehören, prüft der Controller.
 */
final class FormIdRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',            v::optional(v::stringVal()), false),
            v::key('antragstyp_id', v::optional(v::stringVal()), false),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys(['id', 'antragstyp_id', 'keySet'], 'Ungültige Antragstyp-ID');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, antragstyp_id:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'            => (int) ($input['id'] ?? 0),
            'antragstyp_id' => (int) ($input['antragstyp_id'] ?? 0),
        ];
    }
}
