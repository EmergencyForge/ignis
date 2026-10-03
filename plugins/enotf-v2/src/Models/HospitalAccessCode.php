<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `intra_edivi_hospital_access_codes`: Zugangscode je Klinik-POI für
 * das Verfügbarkeits-Update-Interface (eine Zeile pro POI, UNIQUE).
 *
 * ACHTUNG: `code` liegt im KLARTEXT in der DB,
 * bewusst so aus v1 übernommen, damit beide Versionen dieselben Codes
 * akzeptieren. Nicht in Logs oder API-Antworten leaken.
 */
class HospitalAccessCode extends Model
{
    protected $table = 'intra_edivi_hospital_access_codes';

    /**
     * @return BelongsTo<EdiviPoi, $this>
     */
    public function poi(): BelongsTo
    {
        return $this->belongsTo(EdiviPoi::class, 'poi_id');
    }
}
