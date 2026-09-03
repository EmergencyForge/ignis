<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_medikamente` — der Wirkstoff-Katalog
 * für die Medikamentengabe im eNOTF-Protokoll.
 *
 * `wirkstoff` ist unique; `dosierungen` enthält die vordefinierten
 * Dosierungsvorschläge als Text. Deaktivierte Wirkstoffe bleiben für
 * Alt-Protokolle erhalten (`active = 0`).
 */
class EdiviMedikament extends Model
{
    protected $table = 'intra_edivi_medikamente';
}
