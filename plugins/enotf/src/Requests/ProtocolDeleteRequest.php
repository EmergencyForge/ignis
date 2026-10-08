<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Protokoll in der QM-Liste ausblenden (POST /enotf/admin/delete).
 *
 * `return` ist die Listenadresse mit Suche, Sortierung und Seite. Ob sie
 * wirklich auf die Liste zeigt, prüft der Controller, bevor er dorthin
 * zurückleitet.
 */
final class ProtocolDeleteRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',     v::optional(v::stringVal()), false),
            v::key('return', v::optional(v::stringVal()), false),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys(['id', 'return', 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, return:string}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'     => (int) ($input['id'] ?? 0),
            'return' => (string) ($input['return'] ?? ''),
        ];
    }
}
