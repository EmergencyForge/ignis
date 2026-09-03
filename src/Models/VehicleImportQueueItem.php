<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_fahrzeuge_import_queue` — vom FiveM-Server
 * gemeldete Fahrzeuge, die auf Admin-Entscheidung (importieren,
 * überschreiben, zusammenführen, ignorieren) warten.
 *
 * Status: pending → accepted | rejected.
 *
 * @property int         $id
 * @property string|null $emd_vehicle_id
 * @property string      $name
 * @property string      $identifier
 * @property string      $veh_type
 * @property int         $rd_type
 * @property string|null $job
 * @property string      $status
 * @property int|null    $processed_by
 */
class VehicleImportQueueItem extends Model
{
    protected $table = 'intra_fahrzeuge_import_queue';

    protected $casts = [
        'id'      => 'integer',
        'rd_type' => 'integer',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
