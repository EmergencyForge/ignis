<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/system/modules.
 *
 * Felder:
 *   - modules (optional, Liste von Modul-IDs): fehlt, wenn kein Häkchen
 *             gesetzt ist; ob es die Module gibt, prüft ModuleSelection
 */
class SaveModulesRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('modules', v::arrayType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'modules' => 'Die Module müssen als Liste kommen.',
            'keySet'  => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'modules' => array_values(array_filter(
                (array) ($input['modules'] ?? []),
                static fn (mixed $id): bool => is_string($id) && $id !== '',
            )),
        ];
    }
}
