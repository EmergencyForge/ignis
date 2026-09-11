<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi_hospital_availability` — Verfügbarkeitsstatus je
 * Fachabteilung (genau eine Zeile pro Abteilung, UNIQUE department_id).
 *
 * Status-Enum + Anzeige-Semantik:
 *   not_staffed          → grau  („Nicht besetzt")
 *   available            → grün
 *   partially_available  → gelb  („Hohe Auslastung")
 *   full                 → rot   („Abgemeldet")
 */
class HospitalAvailability extends Model
{
    protected $table = 'intra_edivi_hospital_availability';

    public const STATUS_NOT_STAFFED = 'not_staffed';
    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_PARTIAL     = 'partially_available';
    public const STATUS_FULL        = 'full';

    public const STATUS_LABELS = [
        self::STATUS_NOT_STAFFED => 'Nicht besetzt',
        self::STATUS_AVAILABLE   => 'Verfügbar',
        self::STATUS_PARTIAL     => 'Hohe Auslastung',
        self::STATUS_FULL        => 'Abgemeldet',
    ];

    public function department()
    {
        return $this->belongsTo(HospitalDepartment::class, 'department_id');
    }
}
