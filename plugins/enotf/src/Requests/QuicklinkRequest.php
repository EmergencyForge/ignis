<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Die Formulare der eNOTF-Quicklinks (POST /settings/enotf/create, /update
 * und /delete). Das Löschen schickt nur `id`.
 *
 * Leere Pflichtfelder und eine fehlende `id` meldet der Controller, beim
 * Ändern mit einem anderen Text als beim Anlegen.
 */
final class QuicklinkRequest extends FormRequest
{
    private const FELDER = ['id', 'title', 'url', 'icon', 'category', 'sort_order', 'col_width', 'active'];

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
     * @return array{id:int, title:string, url:string, icon:string, category:string, sort_order:int, col_width:string, active:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'         => (int) ($input['id'] ?? 0),
            'title'      => trim((string) ($input['title'] ?? '')),
            'url'        => trim((string) ($input['url'] ?? '')),
            'icon'       => trim((string) ($input['icon'] ?? 'fa-solid fa-link')),
            'category'   => trim((string) ($input['category'] ?? 'schnellzugriff')),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'col_width'  => trim((string) ($input['col_width'] ?? 'col-6')),
            'active'     => isset($input['active']) ? 1 : 0,
        ];
    }
}
