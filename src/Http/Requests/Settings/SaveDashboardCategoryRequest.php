<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Kategorie der Dashboard-Konfiguration
 * (`intra_dashboard_categories`).
 *
 * `id` fehlt beim Anlegen und steht beim Ändern drin; ob es dann größer
 * als null sein muss, weiß der Controller.
 */
class SaveDashboardCategoryRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('title',    v::stringType()->notBlank()->length(1, 255)),
            v::key('priority', v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Der Titel darf nicht leer sein.',
            'length'   => 'Der Titel ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Die Priorität muss eine Zahl sein.',
            'between'  => 'Die Priorität muss zwischen {{minValue}} und {{maxValue}} liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'       => (int) ($input['id'] ?? 0),
            'title'    => trim((string) $input['title']),
            'priority' => (int) ($input['priority'] ?? 0),
        ];
    }
}
