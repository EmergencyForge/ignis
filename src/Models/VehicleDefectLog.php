<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fahrzeuge_defect_log` — Verlaufseinträge
 * (created/updated/resolved/vehicle_disabled/...) zu einer Defektmeldung.
 *
 * `created_at` wird von der Datenbank per Default gesetzt.
 *
 * @property int         $id
 * @property int         $defect_id
 * @property int         $user_id
 * @property string      $action
 * @property string|null $details
 */
class VehicleDefectLog extends Model
{
    protected $table = 'intra_fahrzeuge_defect_log';

    protected $casts = [
        'id'        => 'integer',
        'defect_id' => 'integer',
        'user_id'   => 'integer',
    ];

    public function defect(): BelongsTo
    {
        return $this->belongsTo(VehicleDefect::class, 'defect_id', 'id');
    }
}
