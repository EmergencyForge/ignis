<?php

declare(strict_types=1);

namespace Plugin\Calendar\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Löschen (POST /calendar/delete) und Zu- oder Absagen
 * (POST /calendar/respond) eines Termins.
 *
 * Die Kennung steht normalerweise in der Adresse, `id` im Post ist der
 * Rückfall. Ob `response` ein erlaubter Wert ist, prüft der Controller.
 */
final class EventActionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',       v::optional(v::stringVal()), false),
            v::key('response', v::optional(v::stringVal()), false),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys(['id', 'response', 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:mixed, response:string}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'       => $input['id'] ?? null,
            'response' => (string) ($input['response'] ?? ''),
        ];
    }
}
