<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `intra_edivi_qmlog`: QM-Kommentare und Statuswechsel-Log.
 *
 * FK `protokoll_id` → intra_edivi.id (CASCADE). `log_aktion`:
 *   0 = Freitext-Kommentar (beim Rendern escapen!)
 *   1 = Statuswechsel, `kommentar` enthält bei Altdaten HTML
 *       (ignis-chip) und wird von v1 unescaped gerendert. v2 soll
 *       neue Statuswechsel als Enum ablegen, Altdaten aber weiterhin
 *       darstellen können.
 *
 * `timestamp` hat DB-Default CURRENT_TIMESTAMP.
 */
class EdiviQmLog extends Model
{
    protected $table = 'intra_edivi_qmlog';

    public const AKTION_KOMMENTAR     = 0;
    public const AKTION_STATUSWECHSEL = 1;

    /**
     * @return BelongsTo<Edivi, $this>
     */
    public function protokoll(): BelongsTo
    {
        return $this->belongsTo(Edivi::class, 'protokoll_id');
    }
}
