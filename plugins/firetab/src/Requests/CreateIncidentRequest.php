<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein neuer Einsatz (POST /firetab/create).
 *
 * Die Pflichtfelder prüft der Controller: er zeigt alle fehlenden auf
 * einmal im Formular an, nicht nur das erste. `location_x`/`location_y`
 * hat das Formular nicht, der Controller übernimmt sie aber, wenn sie
 * mitkommen.
 */
final class CreateIncidentRequest extends FormRequest
{
    private const FELDER = [
        'incident_number', 'location', 'keyword', 'date', 'time', 'leader_id', 'notes',
        'caller_name', 'caller_contact', 'owner_name', 'owner_contact', 'location_x', 'location_y',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(...array_map(
            static fn (string $feld): Validatable => v::key($feld, v::optional(v::stringVal()), false),
            self::FELDER,
        ));
    }

    protected static function messages(): array
    {
        return array_fill_keys([...self::FELDER, 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{incident_number:string, location:string, keyword:string, date:string, time:string, leader_id:int|null, notes:string, caller_name:string, caller_contact:string, owner_name:string, owner_contact:string, location_x:float|null, location_y:float|null}
     */
    protected static function cast(array $input): array
    {
        return [
            'incident_number' => trim((string) ($input['incident_number'] ?? '')),
            'location'        => trim((string) ($input['location'] ?? '')),
            'keyword'         => trim((string) ($input['keyword'] ?? '')),
            'date'            => (string) ($input['date'] ?? ''),
            'time'            => (string) ($input['time'] ?? ''),
            'leader_id'       => !empty($input['leader_id']) ? (int) $input['leader_id'] : null,
            'notes'           => trim((string) ($input['notes'] ?? '')),
            'caller_name'     => trim((string) ($input['caller_name'] ?? '')),
            'caller_contact'  => trim((string) ($input['caller_contact'] ?? '')),
            'owner_name'      => trim((string) ($input['owner_name'] ?? '')),
            'owner_contact'   => trim((string) ($input['owner_contact'] ?? '')),
            'location_x'      => !empty($input['location_x']) ? (float) $input['location_x'] : null,
            'location_y'      => !empty($input['location_y']) ? (float) $input['location_y'] : null,
        ];
    }
}
