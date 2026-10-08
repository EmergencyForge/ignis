<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Alle Aktionen am Einsatz über POST /firetab/actions: die Formulare der
 * Register, der QM-Status, Abschluss, Archiv und die ASU-Protokolle aus
 * asu.js.
 *
 * Alle Formulare teilen sich die Adresse und unterscheiden sich nur in
 * `action`, deshalb kennt diese Klasse die Felder aller Aktionen. Was eine
 * Aktion verlangt, prüft ihre Methode im Controller, jede mit ihrer
 * eigenen Meldung.
 *
 * `incident_id` und `return_tab` bleiben null, wenn sie fehlen: der
 * Controller nimmt dann `id` und `tab` aus der Adresse.
 */
final class IncidentActionRequest extends FormRequest
{
    private const FELDER = [
        'action', 'incident_id', 'return_tab',
        'vehicle_id', 'vehicle_name', 'vehicle_identifier', 'radio_name', 'vehicle_row_id',
        'rt_date', 'rt_time', 'text', 'sitrep_attached_vehicle_id',
        'status', 'notes',
        'edit_location', 'edit_keyword', 'edit_incident_number', 'edit_date', 'edit_time',
        'edit_leader_id', 'edit_caller_name', 'edit_caller_contact', 'edit_owner_name', 'edit_owner_contact',
        'asu_id', 'asu_data',
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
     * @return array<string,mixed>
     */
    protected static function cast(array $input): array
    {
        $text = static fn (string $feld): string => trim((string) ($input[$feld] ?? ''));
        $id   = static fn (string $feld): ?int => !empty($input[$feld]) ? (int) $input[$feld] : null;

        return [
            'action'      => (string) ($input['action'] ?? ''),
            'incident_id' => $input['incident_id'] ?? null,
            'return_tab'  => isset($input['return_tab']) ? (string) $input['return_tab'] : null,

            'vehicle_id'         => $id('vehicle_id'),
            'vehicle_name'       => $text('vehicle_name'),
            'vehicle_identifier' => $text('vehicle_identifier'),
            'radio_name'         => $text('radio_name'),
            'vehicle_row_id'     => (int) ($input['vehicle_row_id'] ?? 0),

            'rt_date'                    => (string) ($input['rt_date'] ?? ''),
            'rt_time'                    => (string) ($input['rt_time'] ?? ''),
            'text'                       => $text('text'),
            'sitrep_attached_vehicle_id' => $id('sitrep_attached_vehicle_id'),

            'status' => (int) ($input['status'] ?? 0),
            'notes'  => $text('notes'),

            'edit_location'        => $text('edit_location'),
            'edit_keyword'         => $text('edit_keyword'),
            'edit_incident_number' => $text('edit_incident_number'),
            'edit_date'            => (string) ($input['edit_date'] ?? ''),
            'edit_time'            => (string) ($input['edit_time'] ?? ''),
            'edit_leader_id'       => $id('edit_leader_id'),
            'edit_caller_name'     => $text('edit_caller_name'),
            'edit_caller_contact'  => $text('edit_caller_contact'),
            'edit_owner_name'      => $text('edit_owner_name'),
            'edit_owner_contact'   => $text('edit_owner_contact'),

            'asu_id'   => (int) ($input['asu_id'] ?? 0),
            'asu_data' => (string) ($input['asu_data'] ?? ''),
        ];
    }
}
