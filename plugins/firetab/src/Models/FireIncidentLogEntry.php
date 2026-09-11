<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_log` — Aktivitätslog eines
 * Einsatzes (Fahrzeug angemeldet, Status geändert, Sitrep erfasst, ...).
 *
 * `created_at` kommt per DB-Default (CURRENT_TIMESTAMP). `created_by` ist
 * seit einer späteren ALTER-Migration nullable.
 *
 * @property int         $id
 * @property int         $incident_id
 * @property string      $action_type
 * @property string      $action_description
 * @property int|null    $vehicle_id   FK → intra_fahrzeuge
 * @property int|null    $operator_id  FK → intra_mitarbeiter
 * @property int|null    $created_by
 * @property string|null $created_at
 */
class FireIncidentLogEntry extends Model
{
    protected $table = 'intra_fire_incident_log';

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
