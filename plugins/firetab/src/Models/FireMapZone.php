<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_map_zones` — Zonen (Polygone)
 * auf der taktischen Einsatzkarte.
 *
 * `points` hält die Polygon-Punkte als JSON-String; die Konsumenten
 * (Lagekarten-JS) parsen selbst, daher kein Array-Cast.
 *
 * @property int         $id
 * @property int         $incident_id
 * @property string      $name
 * @property string|null $description
 * @property string      $points       JSON-Array der Polygon-Punkte
 * @property string      $color
 * @property int|null    $created_by   FK → intra_mitarbeiter
 * @property int|null    $vehicle_id   FK → intra_fahrzeuge
 * @property int|null    $operator_id  FK → intra_mitarbeiter
 * @property string      $created_at
 */
class FireMapZone extends Model
{
    protected $table = 'intra_fire_incident_map_zones';

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
