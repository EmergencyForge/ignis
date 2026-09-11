<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_sitreps` — Lagemeldungen
 * (Sitreps) zu einem Einsatz.
 *
 * `created_at` kommt per DB-Default (CURRENT_TIMESTAMP).
 *
 * @property int         $id
 * @property int         $incident_id
 * @property string      $report_time
 * @property string      $text
 * @property string|null $vehicle_radio_name
 * @property int|null    $vehicle_id   FK → intra_fahrzeuge
 * @property string|null $source       via ALTER: Herkunft (z. B. Sync)
 * @property string      $created_at
 * @property int|null    $created_by   FK → intra_users
 */
class FireSitrep extends Model
{
    protected $table = 'intra_fire_incident_sitreps';

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
