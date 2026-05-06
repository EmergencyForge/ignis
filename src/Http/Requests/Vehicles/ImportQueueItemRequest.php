<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation für VehicleImportController — Aktionen `import`, `overwrite`,
 * `merge`, `ignore`.
 *
 * Gemeinsamer Input-Shape:
 *   - `queue_id`    — Queue-Eintrag (immer Pflicht)
 *   - `existing_id` — nur bei overwrite/merge Pflicht (Controller prüft das)
 *   - `veh_type`, `rd_type`, `allowed_jobs` — optionale Überschreibungen
 *     der Queue-Vorschläge (für `ignore` nicht genutzt)
 *
 * Kategorie-ähnliche Enforcement gibt es hier nicht: `veh_type` ist ein
 * freier String (Fahrzeug-Typ-Name), `rd_type` ist 0–3.
 */
class ImportQueueItemRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('queue_id',     v::stringVal()->intVal()->positive()),
            v::key('existing_id',  v::optional(v::stringVal()->intVal()->positive()), false),
            v::key('veh_type',     v::optional(v::stringType()->length(0, 64)), false),
            v::key('rd_type',      v::optional(v::stringVal()->intVal()->between(0, 3, true)), false),
            v::key('allowed_jobs', v::optional(v::stringType()->length(0, 500)), false),
            // Routing-Felder
            v::key('action',       v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'queue_id'    => 'Queue-ID muss eine positive Zahl sein.',
            'existing_id' => 'Existing-ID muss eine positive Zahl sein.',
            'rd_type'     => 'rd_type muss zwischen 0 und 3 liegen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'queue_id'     => (int) $input['queue_id'],
            'existing_id'  => !empty($input['existing_id']) ? (int) $input['existing_id'] : null,
            'veh_type'     => isset($input['veh_type'])     ? trim((string) $input['veh_type'])     : null,
            'rd_type'      => isset($input['rd_type'])      ? (int) $input['rd_type']               : null,
            'allowed_jobs' => isset($input['allowed_jobs']) ? (trim((string) $input['allowed_jobs']) ?: null) : null,
        ];
    }
}
