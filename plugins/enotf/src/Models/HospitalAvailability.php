<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_hospital_availability` — aktueller
 * Verfügbarkeits-Status je Fachrichtung (unique auf `department_id`).
 *
 * Status-Enum: not_staffed | available | partially_available | full.
 * `updated_at` wird von der DB gepflegt (DEFAULT/ON UPDATE
 * CURRENT_TIMESTAMP), Schreibzugriffe laufen als Upsert.
 */
class HospitalAvailability extends Model
{
    protected $table = 'intra_edivi_hospital_availability';
}
