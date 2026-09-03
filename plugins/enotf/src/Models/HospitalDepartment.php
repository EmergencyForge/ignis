<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_hospital_departments` — Fachrichtungen
 * eines Krankenhaus-POIs (z. B. ZNA/INA, Schockraum, Intensivstation).
 *
 * `sort_order` steuert die Anzeige-Reihenfolge im Verfügbarkeits-Portal;
 * der Verfügbarkeits-Status selbst liegt in `intra_edivi_hospital_availability`
 * (eine Zeile pro Department).
 */
class HospitalDepartment extends Model
{
    protected $table = 'intra_edivi_hospital_departments';
}
