<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Formulare, die nur Kennungen schicken: Löschen eines Medikaments, eines
 * POIs oder einer Fachrichtung und das Zurücksetzen der Verfügbarkeiten.
 * Welche der beiden Kennungen größer null sein muss, weiß nur der
 * Controller.
 */
final class RecordIdRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('id',     v::optional(v::stringVal()->intVal()), false),
            v::key('poi_id', v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'id'     => 'Ungültige ID.',
            'poi_id' => 'Ungültige POI-ID.',
            'keySet' => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, poi_id:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'     => (int) ($input['id'] ?? 0),
            'poi_id' => (int) ($input['poi_id'] ?? 0),
        ];
    }
}
