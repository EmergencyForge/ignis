<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi_share_requests` — Protokoll-Übergabe zwischen Fahrzeugen.
 *
 * `source_protocol_id` referenziert intra_edivi.id (kein echter FK).
 * `status`: pending | accepted | rejected | cancelled.
 * `action_taken` (nach Annahme): merged | new_protocol; bei „new"
 * steht die neue Einsatznummer in `new_enr`.
 */
class EdiviShareRequest extends Model
{
    protected $table = 'intra_edivi_share_requests';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
