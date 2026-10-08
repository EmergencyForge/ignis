<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Die Formulare der Quicklink-Kategorien (POST /settings/enotf/kategorien/
 * create, /update und /delete). Das Löschen schickt nur `id`.
 *
 * Leere Pflichtfelder, eine fehlende `id` und doppelte Slugs meldet der
 * Controller.
 */
final class QuicklinkCategoryRequest extends FormRequest
{
    private const FELDER = ['id', 'name', 'slug', 'sort_order', 'active'];

    protected static function rules(): Validatable
    {
        return v::keySet(...array_map(
            static fn (string $feld): Validatable => v::key($feld, v::optional(v::stringVal()), false),
            self::FELDER,
        ));
    }

    protected static function messages(): array
    {
        return array_fill_keys([...self::FELDER, 'keySet'], 'Ungültige Daten.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, name:string, slug:string, sort_order:int, active:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'         => (int) ($input['id'] ?? 0),
            'name'       => trim((string) ($input['name'] ?? '')),
            'slug'       => strtolower(trim((string) ($input['slug'] ?? ''))),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'active'     => isset($input['active']) ? 1 : 0,
        ];
    }
}
