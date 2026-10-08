<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Neuer Zugangscode eines Krankenhauses für das Verfügbarkeitsportal.
 * Den Code erzeugt der Dialog im Browser, der Server nimmt ihn, wie er
 * kommt.
 */
final class PoiAccessCodeRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('poi_id',   v::stringVal()->intVal()->positive()),
            v::key('new_code', v::stringType()->notBlank()->length(1, 255)),
        );
    }

    protected static function messages(): array
    {
        return [
            'poi_id'   => 'POI ID oder Code fehlt.',
            'new_code' => 'POI ID oder Code fehlt.',
            'keySet'   => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{poi_id:int, new_code:string}
     */
    protected static function cast(array $input): array
    {
        return [
            'poi_id'   => (int) $input['poi_id'],
            'new_code' => trim((string) $input['new_code']),
        ];
    }
}
