<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eingaben des Vorlagen-Editors. `content` kommt als JSON-Text aus dem
 * versteckten Feld des Editors; ob darin eine brauchbare Vorlage steht,
 * prüft der Controller mit dem SectionGuard — hier geht es nur darum, dass
 * überhaupt etwas ankommt.
 */
class SaveDocumentTemplateRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name', v::stringType()->notBlank()->length(1, 150)),
            v::key('category', v::optional(v::stringType()->length(0, 100)), false),
            v::key('content', v::stringType()->notBlank()),
            // Der Token steht im selben Formular; keySet weist sonst den
            // ganzen Satz als unbekannten Schluessel zurueck.
            v::key('csrf_token', v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'name'     => 'Name ist erforderlich (höchstens 150 Zeichen).',
            'category' => 'Kategorie darf höchstens 100 Zeichen lang sein.',
            'content'  => 'Vorlageninhalt darf nicht leer sein.',
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{name: string, category: string|null, content: string}
     */
    protected static function cast(array $input): array
    {
        $category = trim((string) ($input['category'] ?? ''));

        return [
            'name'     => trim((string) ($input['name'] ?? '')),
            'category' => $category !== '' ? $category : null,
            'content'  => (string) ($input['content'] ?? ''),
        ];
    }
}
