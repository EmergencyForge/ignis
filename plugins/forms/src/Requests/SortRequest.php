<?php

declare(strict_types=1);

namespace Plugin\Forms\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Die Reihenfolge der Antragstypen (POST /settings/forms/sort,
 * `sortierung[12]=3`) und der Felder eines Typs (POST /settings/forms/edit,
 * `feld_sortierung[12]=3` mit `update_felder_sortierung`).
 *
 * Kennung und Wert müssen Zahlen sein, sonst fällt der Eintrag still weg:
 * ein Schlüssel wie `sortierung[abc]` landete sonst als `WHERE id = 0` in
 * der Abfrage.
 */
final class SortRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('sortierung',               v::optional(v::arrayType()), false),
            v::key('feld_sortierung',          v::optional(v::arrayType()), false),
            v::key('update_felder_sortierung', v::optional(v::stringVal()), false),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys(['sortierung', 'feld_sortierung', 'update_felder_sortierung', 'keySet'], 'Ungültige Sortierung.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{sortierung:array<int,int>, feld_sortierung:array<int,int>}
     */
    protected static function cast(array $input): array
    {
        return [
            'sortierung'      => self::tabelle($input['sortierung'] ?? null),
            'feld_sortierung' => self::tabelle($input['feld_sortierung'] ?? null),
        ];
    }

    /**
     * @return array<int,int>
     */
    private static function tabelle(mixed $roh): array
    {
        if (!is_array($roh)) {
            return [];
        }

        $out = [];
        foreach ($roh as $id => $sortierung) {
            if (!is_numeric($id) || !is_numeric($sortierung)) {
                continue;
            }
            if ((int) $id > 0) {
                $out[(int) $id] = (int) $sortierung;
            }
        }

        return $out;
    }
}
