<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_hospital_access_codes` — Zugangscodes
 * für das Klinik-Verfügbarkeits-Portal (eine Zeile pro POI, unique auf
 * `poi_id`; Code-Vergleich läuft im Klartext, siehe Migration
 * ..._alter_intra_edivi_hospital_access_codes_21012026_plaintext).
 */
class HospitalAccessCode extends Model
{
    protected $table = 'intra_edivi_hospital_access_codes';
}
