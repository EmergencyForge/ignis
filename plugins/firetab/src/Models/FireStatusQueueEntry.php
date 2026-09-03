<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_fire_status_queue` — Queue für ausgehende
 * Fahrzeug-Status-Änderungen, die der FiveM-Server pollt.
 *
 * Abgeholte Einträge werden über `delivered = 1` markiert (at-most-once).
 * `created_at` kommt per DB-Default (CURRENT_TIMESTAMP).
 *
 * @property int         $id
 * @property int         $vehicle_id       FK → intra_fahrzeuge
 * @property string      $vehicle_name
 * @property string|null $incident_number
 * @property string      $new_status
 * @property string|null $created_at
 * @property int         $delivered
 */
class FireStatusQueueEntry extends Model
{
    protected $table = 'intra_fire_status_queue';
}
