<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_pois` — POIs (Krankenhäuser, Wachen,
 * Einsatzorte) für eNOTF-Transportziele und die Klinik-Schnittstelle.
 *
 * Nach der Konsolidierungs-Migration (vormals `intra_edivi_ziele`) tragen
 * alte Datensätze ihren früheren Schlüssel in `legacy_identifier`; neue
 * Protokolle referenzieren POIs als `poi_<id>`. Auflösung eines
 * Transportziels prüft deshalb beide Wege.
 *
 * `created_at`/`updated_at` werden von der DB gepflegt (DEFAULT/ON UPDATE
 * CURRENT_TIMESTAMP), nicht von Eloquent — daher bleiben die
 * Basisklassen-Defaults ($timestamps = false) richtig.
 */
class EdiviPoi extends Model
{
    protected $table = 'intra_edivi_pois';
}
