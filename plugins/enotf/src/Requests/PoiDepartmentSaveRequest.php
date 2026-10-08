<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Fachrichtung eines Krankenhauses, angelegt oder geändert
 * (`intra_edivi_hospital_departments`). `id` fehlt beim Anlegen; ob sie
 * und `poi_id` größer null sind, prüft der Controller.
 */
final class PoiDepartmentSaveRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',       v::stringType()->notBlank()->length(1, 255)),
            v::key('sort_order', v::optional(v::stringVal()->intVal()), false),
            v::key('poi_id',     v::optional(v::stringVal()->intVal()), false),
            v::key('id',         v::optional(v::stringVal()->intVal()), false),
        );
    }

    /** Je Feld eine Meldung, siehe MedikamentSaveRequest::messages(). */
    protected static function messages(): array
    {
        return [
            'name'       => 'Der Name der Fachrichtung ist Pflicht und darf höchstens 255 Zeichen lang sein.',
            'sort_order' => 'Die Sortierung muss eine Zahl sein.',
            'poi_id'     => 'Ungültige POI-ID.',
            'id'         => 'Ungültige ID.',
            'keySet'     => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, poi_id:int, name:string, sort_order:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'         => (int) ($input['id'] ?? 0),
            'poi_id'     => (int) ($input['poi_id'] ?? 0),
            'name'       => trim((string) $input['name']),
            'sort_order' => (int) ($input['sort_order'] ?? 999),
        ];
    }
}
