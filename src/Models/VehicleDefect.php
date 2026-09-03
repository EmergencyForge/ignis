<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_fahrzeuge_defects` — Fahrzeug-Defektmeldungen.
 *
 * Status-Lifecycle: open → in_progress/deferred → resolved.
 * `vehicle_operable = 0` markiert das Fahrzeug als nicht einsatzfähig und
 * deaktiviert es, solange die Meldung offen ist.
 *
 * @property int         $id
 * @property int         $vehicle_id
 * @property string      $title
 * @property string|null $description
 * @property string      $category
 * @property int         $vehicle_operable
 * @property string      $status
 * @property int|null    $reported_by
 * @property int|null    $assigned_to
 * @property int|null    $resolved_by
 */
class VehicleDefect extends Model
{
    protected $table = 'intra_fahrzeuge_defects';

    protected $casts = [
        'id'         => 'integer',
        'vehicle_id' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function logEntries(): HasMany
    {
        return $this->hasMany(VehicleDefectLog::class, 'defect_id', 'id');
    }
}
