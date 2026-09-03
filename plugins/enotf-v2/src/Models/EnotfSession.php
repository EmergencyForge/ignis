<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `intra_enotf_sessions` — Crew-Session pro Fahrzeug (max. eine aktive
 * je vehicle_identifier; alte Sessions bleiben mit active=0 als
 * Historie stehen).
 *
 * Schreibzugriffe laufen im Login-Flow bewusst über den v1-Service
 * `Plugin\Enotf\EnotfSession`, damit v1 und v2 exakt dieselbe
 * DB-Semantik haben. Dieses Model ist der Lese-Zugang.
 *
 * created_at/updated_at werden von der DB gepflegt (Defaults/ON UPDATE),
 * daher timestamps=false aus der Basisklasse.
 */
class EnotfSession extends Model
{
    protected $table = 'intra_enotf_sessions';

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function members(): HasMany
    {
        return $this->hasMany(EnotfSessionMember::class, 'session_id');
    }
}
