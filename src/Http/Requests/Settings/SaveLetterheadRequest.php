<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/documents/letterheads/create und .../{id}. Ob
 * `content` ein Dokumentpaket ist, prüft LetterheadController.
 */
class SaveLetterheadRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name', v::stringType()->notBlank()->length(1, 150)),
            v::key('content', v::stringType()->notBlank()),
        );
    }

    protected static function messages(): array
    {
        return [
            'name'    => 'Name ist erforderlich (max. 150 Zeichen).',
            'content' => 'Der Briefbogen ist leer.',
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{name:string, content:string}
     */
    protected static function cast(array $input): array
    {
        return [
            'name'    => trim((string) $input['name']),
            'content' => (string) $input['content'],
        ];
    }
}
