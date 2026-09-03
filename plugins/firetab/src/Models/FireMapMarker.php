<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_map_markers` — taktische Marker
 * auf der Einsatz-Lagekarte.
 *
 * Position in Prozent (0-100). Neben freien Markern (marker_type) tragen
 * Marker optional taktische Zeichen (grundzeichen, organisation,
 * fachaufgabe, einheit, symbol, typ, text).
 *
 * `created_at` hat keinen DB-Default und wird beim Insert explizit gesetzt.
 *
 * @property int         $id
 * @property int         $incident_id
 * @property string      $marker_type
 * @property float       $pos_x        Prozent 0-100
 * @property float       $pos_y        Prozent 0-100
 * @property string|null $description
 * @property string|null $grundzeichen
 * @property string|null $organisation
 * @property string|null $fachaufgabe
 * @property string|null $einheit
 * @property string|null $symbol
 * @property string|null $typ
 * @property string|null $text
 * @property string|null $name
 * @property int|null    $created_by   FK → intra_mitarbeiter
 * @property int|null    $vehicle_id   FK → intra_fahrzeuge
 * @property int|null    $operator_id  FK → intra_mitarbeiter
 * @property string      $created_at
 */
class FireMapMarker extends Model
{
    protected $table = 'intra_fire_incident_map_markers';

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
