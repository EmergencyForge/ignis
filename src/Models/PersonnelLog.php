<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_mitarbeiter_log` — Log-Einträge auf
 * Mitarbeiterprofilen (Notizen, Rank-Änderungen, Dokumente, …).
 *
 * Typ-Konstanten und die zugehörige Semantik liegen im
 * App\Personnel\PersonalLogManager.
 *
 * @property int         $logid
 * @property int         $profilid
 * @property int         $type
 * @property string      $content
 * @property string      $paneluser
 * @property string|null $metadata   JSON-String
 * @property string      $datetime
 */
class PersonnelLog extends Model
{
    protected $table = 'intra_mitarbeiter_log';

    protected $primaryKey = 'logid';

    protected $casts = [
        'logid'    => 'integer',
        'profilid' => 'integer',
        'type'     => 'integer',
    ];
}
