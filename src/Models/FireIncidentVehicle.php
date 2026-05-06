<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_vehicles` — Fahrzeuge die an
 * einem Feuerwehr-Einsatz beteiligt sind/waren.
 *
 * Hat nur `created_at` (kein updated_at), daher erbt es von der intraRP-
 * Base-Model-Klasse mit `$timestamps = false`.
 *
 * @property int         $id
 * @property int         $incident_id
 * @property int|null    $vehicle_id           FK → intra_fahrzeuge
 * @property string|null $vehicle_name
 * @property string|null $vehicle_identifier
 * @property bool        $from_other_org
 * @property string|null $radio_name
 * @property string|null $status               via spätere ALTER-Migration
 * @property \DateTime   $created_at
 * @property int|null    $created_by
 */
class FireIncidentVehicle extends Model
{
    protected $table = 'intra_fire_incident_vehicles';

    protected $casts = [
        'id'             => 'integer',
        'incident_id'    => 'integer',
        'vehicle_id'     => 'integer',
        'from_other_org' => 'boolean',
        'created_by'     => 'integer',
        'created_at'     => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
