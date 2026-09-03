<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_qmlog` — QM-Kommentare und Status-
 * Aktionen je Protokoll.
 *
 * `log_aktion`: 0 = Kommentar, 1 = Statusänderung (kommentar enthält dann
 * das Status-Chip-HTML). `timestamp` hat DB-Default CURRENT_TIMESTAMP.
 * Einträge hängen per FK (CASCADE) am Protokoll.
 */
class EdiviQmLog extends Model
{
    protected $table = 'intra_edivi_qmlog';
}
