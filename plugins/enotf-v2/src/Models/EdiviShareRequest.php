<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * `intra_edivi_share_requests`: Protokoll-Übergabe zwischen Fahrzeugen.
 *
 * `source_protocol_id` referenziert intra_edivi.id (kein echter FK).
 * `status`: pending | accepted | rejected | cancelled.
 * `action_taken` (nach Annahme): merged | new_protocol; bei „new"
 * steht die neue Einsatznummer in `new_enr`.
 *
 * @method static Builder<static> pending()
 */
class EdiviShareRequest extends Model
{
    protected $table = 'intra_edivi_share_requests';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param Builder<self> $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }
}
