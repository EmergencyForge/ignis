<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Anmeldung auf einem Fahrzeug (POST /firetab/login-vehicle).
 *
 * Ob Fahrzeug und Mitarbeiter gewählt sind und existieren, prüft der
 * Controller, er meldet beides getrennt.
 */
final class VehicleLoginRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('vehicle_id',  v::optional(v::stringVal()), false),
            v::key('operator_id', v::optional(v::stringVal()), false),
        );
    }

    protected static function messages(): array
    {
        return array_fill_keys(['vehicle_id', 'operator_id', 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{vehicle_id:int, operator_id:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'vehicle_id'  => (int) ($input['vehicle_id'] ?? 0),
            'operator_id' => (int) ($input['operator_id'] ?? 0),
        ];
    }
}
