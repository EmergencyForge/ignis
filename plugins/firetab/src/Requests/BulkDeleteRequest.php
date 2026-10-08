<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Die angehakten Einsätze der QM-Liste (POST /firetab/admin/list/delete,
 * `ids[]` aus der Aktionsleiste).
 *
 * Eine Kennung, die keine positive Zahl ist, fällt still weg; ob danach
 * noch etwas übrig ist, entscheidet der Controller.
 */
final class BulkDeleteRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('ids', v::optional(v::arrayType()), false),
        );
    }

    protected static function messages(): array
    {
        return ['ids' => 'Kein Protokoll ausgewählt.', 'keySet' => 'Kein Protokoll ausgewählt.'];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{ids:list<int>}
     */
    protected static function cast(array $input): array
    {
        $raw = is_array($input['ids'] ?? null) ? $input['ids'] : [];

        return [
            'ids' => array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0))),
        ];
    }
}
