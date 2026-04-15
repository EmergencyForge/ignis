<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Validation\FormRequest;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/pois/departments-sort.
 *
 * Body: { "department_id": int, "sort_order": int }
 */
final class PoiDepartmentsSortRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'department_id' => v::intVal()->positive(),
            'sort_order'    => v::intVal()->min(0),
        ];
    }

    protected function messages(): array
    {
        return [
            'department_id' => 'department_id muss eine positive Ganzzahl sein',
            'sort_order'    => 'sort_order muss eine nicht-negative Ganzzahl sein',
        ];
    }
}
