<?php

declare(strict_types=1);

namespace App\Http\Requests\Fire;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/fire/lagekarte?action=create.
 *
 * Lagekarten-Marker werden als relative Prozentwerte in der Karte
 * gespeichert (0–100 auf jeder Achse). Die taktischen Symbol-Felder
 * sind alle optional und werden 1:1 durchgereicht — die Semantik
 * prüft der Client beim Anlegen.
 */
class CreateMarkerRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('incident_id',  v::stringVal()->intVal()->positive()),
            v::key('marker_type',  v::stringType()->notBlank()->length(1, 64)),
            v::key('pos_x',        v::stringVal()->floatVal()->between(0.0, 100.0, true)),
            v::key('pos_y',        v::stringVal()->floatVal()->between(0.0, 100.0, true)),
            v::key('description',  v::optional(v::stringType()->length(0, 500)), false),
            // Taktische Symbol-Felder — alle optional, reine String-Werte
            v::key('grundzeichen', v::optional(v::stringType()->length(0, 64)), false),
            v::key('organisation', v::optional(v::stringType()->length(0, 64)), false),
            v::key('fachaufgabe',  v::optional(v::stringType()->length(0, 64)), false),
            v::key('einheit',      v::optional(v::stringType()->length(0, 64)), false),
            v::key('symbol',       v::optional(v::stringType()->length(0, 64)), false),
            v::key('typ',          v::optional(v::stringType()->length(0, 64)), false),
            v::key('text',         v::optional(v::stringType()->length(0, 500)), false),
            v::key('name',         v::optional(v::stringType()->length(0, 200)), false),
            // Optionaler Fahrzeug-Override (Legende-Auswahl)
            v::key('vehicle_id',   v::optional(v::stringVal()->intVal()), false),
            // Routing-Felder, nicht validiert
            v::key('action',       v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'incident_id' => 'Einsatz-ID muss eine positive Zahl sein.',
            'marker_type' => 'Marker-Typ ist Pflicht.',
            'pos_x'       => 'pos_x muss zwischen 0 und 100 liegen.',
            'pos_y'       => 'pos_y muss zwischen 0 und 100 liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'incident_id'  => (int) $input['incident_id'],
            'marker_type'  => trim((string) $input['marker_type']),
            'pos_x'        => (float) $input['pos_x'],
            'pos_y'        => (float) $input['pos_y'],
            'description'  => trim((string) ($input['description'] ?? '')),
            'grundzeichen' => self::nullIfEmpty($input['grundzeichen'] ?? null),
            'organisation' => self::nullIfEmpty($input['organisation'] ?? null),
            'fachaufgabe'  => self::nullIfEmpty($input['fachaufgabe'] ?? null),
            'einheit'      => self::nullIfEmpty($input['einheit'] ?? null),
            'symbol'       => self::nullIfEmpty($input['symbol'] ?? null),
            'typ'          => self::nullIfEmpty($input['typ'] ?? null),
            'text'         => self::nullIfEmpty($input['text'] ?? null),
            'name'         => self::nullIfEmpty($input['name'] ?? null),
            'vehicle_id'   => isset($input['vehicle_id']) && $input['vehicle_id'] !== '' ? (int) $input['vehicle_id'] : null,
        ];
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
