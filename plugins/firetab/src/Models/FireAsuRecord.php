<?php

declare(strict_types=1);

namespace Plugin\Firetab\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_fire_incident_asu` — Atemschutzüberwachung
 * (ASÜ) pro Einsatz.
 *
 * Ein Datensatz je (incident_id, supervisor) — Unique-Key. Die Trupp-Daten
 * liegen als JSON-Blob in `data`; Konsumenten (ASÜ-UI) parsen selbst.
 *
 * Die Tabelle hat BEIDE Timestamps mit DB-Defaults (CURRENT_TIMESTAMP bzw.
 * ON UPDATE), daher erbt die Klasse direkt von EloquentModel und lässt die
 * DB die Timestamps pflegen ($timestamps = false, damit Eloquent den
 * ON-UPDATE-Mechanismus nicht überschreibt).
 *
 * @property int         $id
 * @property int         $incident_id
 * @property string      $supervisor
 * @property string|null $mission_location
 * @property string|null $mission_date
 * @property string|null $timestamp
 * @property string      $data         JSON-Blob der Trupp-Daten
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class FireAsuRecord extends EloquentModel
{
    protected $table = 'intra_fire_incident_asu';

    public $timestamps = false;

    protected $guarded = [];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class, 'incident_id', 'id');
    }
}
