<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Fachdienst (`intra_mitarbeiter_fdquali`).
 *
 * `sgnr` ist die Nummer des Fachdienstes und muss eine Zahl sein. Bisher
 * machte ein `(int)`-Cast aus jeder Eingabe eine, aus „abc" eben die Null.
 */
class SaveSpecialtyRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('sgnr',     v::stringVal()->intVal()->between(0, 9999)),
            v::key('sgname',   v::stringType()->notBlank()->length(1, 255)),
            v::key('disabled', v::optional(v::stringType()), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        // Unter v::keySet() greifen nur Schlüssel nach Feldname (und
        // `keySet`), nicht nach Regelname wie `notBlank`.
        return [
            'sgnr'     => 'Die Nummer muss eine Zahl zwischen 0 und 9999 sein.',
            'sgname'   => 'Die Bezeichnung darf nicht leer sein und höchstens 255 Zeichen haben.',
            'disabled' => 'Ungültiger Wert für „deaktiviert".',
            'id'       => 'Ungültige ID.',
            'keySet'   => 'Das Formular ist unvollständig oder enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'       => (int) ($input['id'] ?? 0),
            'sgnr'     => (int) $input['sgnr'],
            'sgname'   => trim((string) $input['sgname']),
            'disabled' => isset($input['disabled']) ? 1 : 0,
        ];
    }
}
