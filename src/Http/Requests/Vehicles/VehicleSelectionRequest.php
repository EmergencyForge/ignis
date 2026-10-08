<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für die Aktionsleiste der Fahrzeugliste: POST
 * /settings/vehicles/vehicles/status, /emd-status und /delete.
 *
 * Alle drei schickt dasselbe Formular, nur `formaction` wechselt; deshalb
 * kommen beide Auswahlfelder immer mit, und eine Regelmenge reicht.
 *
 * Felder:
 *   - ids        (Liste, von der Aktionsleiste)
 *   - id         (optional, ein einzelnes Fahrzeug statt `ids`)
 *   - status     (optional, active oder inactive; prüft der Controller)
 *   - emd_status (optional, FMS-Status; prüft der Controller)
 *
 * `ids` in der Ausgabe ist die Liste der Fahrzeug-Ids ohne Doppelte und
 * Nullen.
 */
class VehicleSelectionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('ids',        v::arrayType(), false),
            v::key('id',         v::optional(v::intVal()), false),
            v::key('status',     v::stringType(), false),
            v::key('emd_status', v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'ids'        => 'Die Auswahl muss als Liste kommen.',
            'id'         => 'Ungültige ID.',
            'status'     => 'Unbekannter Status.',
            'emd_status' => 'Unbekannter Status.',
            'keySet'     => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        $ids = array_map('intval', (array) ($input['ids'] ?? []));
        if ($ids === [] && (int) ($input['id'] ?? 0) > 0) {
            $ids = [(int) $input['id']];
        }

        return [
            'ids'        => array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0))),
            'status'     => (string) ($input['status'] ?? ''),
            'emd_status' => (string) ($input['emd_status'] ?? ''),
        ];
    }
}
