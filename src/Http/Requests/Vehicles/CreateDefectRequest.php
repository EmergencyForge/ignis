<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/vehicles/defects-handler?action=create.
 *
 * Meldet einen neuen Fahrzeug-Defekt. `title` ist Pflicht, `description`
 * optional. Die Kategorie-Whitelist ist identisch zur Controller-
 * Konstante `VehicleDefectsController::ALLOWED_CATEGORIES` — wenn dort
 * etwas dazukommt, muss diese Liste ebenfalls erweitert werden.
 *
 * `vehicle_operable` kommt als Checkbox-String vom Formular (`"1"` / `"0"`
 * oder fehlend) — der Cast konvertiert auf int, Default 1 (einsatzfähig).
 */
class CreateDefectRequest extends FormRequest
{
    public const ALLOWED_CATEGORIES = [
        'aufbau_karosserie', 'ausbau', 'batterie', 'beleuchtung', 'bremsen',
        'elektrik', 'fahrwerk', 'getriebe', 'motor', 'reifen',
        'service_pruefintervall', 'signalanlage', 'sonstiges', 'windschutzscheibe',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('vehicle_id',         v::stringVal()->intVal()->positive()),
            v::key('title',              v::stringType()->notBlank()->length(1, 200)),
            v::key('description',        v::optional(v::stringType()->length(0, 5000)), false),
            v::key('category',           v::optional(v::in(self::ALLOWED_CATEGORIES, true)), false),
            v::key('vehicle_operable',   v::optional(v::stringVal()->in(['0', '1'], true)), false),
            v::key('reported_by_name',   v::optional(v::stringType()->length(0, 200)), false),
            // action-Parameter ist Routing-Feld, nicht validiert
            v::key('action',             v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'vehicle_id'       => 'Fahrzeug-ID muss eine positive Zahl sein.',
            'title'            => 'Titel ist Pflicht (1–200 Zeichen).',
            'description'     => 'Beschreibung darf maximal 5000 Zeichen haben.',
            'category'         => 'Ungültige Kategorie.',
            'vehicle_operable' => 'Einsatzfähig muss 0 oder 1 sein.',
            'reported_by_name' => 'Gemeldet-von-Name darf maximal 200 Zeichen haben.',
        ];
    }

    protected static function cast(array $input): array
    {
        $category = $input['category'] ?? 'sonstiges';
        if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $category = 'sonstiges';
        }

        return [
            'vehicle_id'       => (int) $input['vehicle_id'],
            'title'            => trim((string) $input['title']),
            'description'      => trim((string) ($input['description'] ?? '')),
            'category'         => $category,
            'vehicle_operable' => isset($input['vehicle_operable']) ? (int) $input['vehicle_operable'] : 1,
            'reported_by_name' => trim((string) ($input['reported_by_name'] ?? '')),
        ];
    }
}
