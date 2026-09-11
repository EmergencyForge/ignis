<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * `intra_edivi_hospital_departments` — Fachabteilungen je Klinik-POI.
 *
 * FK `poi_id` → intra_edivi_pois.id (CASCADE). `sort_order` Default 999.
 * Beim Anlegen legt v1 sofort eine availability-Zeile mit Status
 * not_staffed an.
 */
class HospitalDepartment extends Model
{
    protected $table = 'intra_edivi_hospital_departments';

    public function poi()
    {
        return $this->belongsTo(EdiviPoi::class, 'poi_id');
    }

    public function availability(): HasOne
    {
        return $this->hasOne(HospitalAvailability::class, 'department_id');
    }
}
