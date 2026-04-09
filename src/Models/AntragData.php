<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_antraege_daten` — Key-Value-Speicher der
 * Formulardaten eines Antrags. Ein Record pro ausgefülltem Feld.
 *
 * @property int    $id
 * @property int    $antrag_id
 * @property string $feldname
 * @property string|null $wert
 * @property-read Antrag $antrag
 */
class AntragData extends Model
{
    protected $table = 'intra_antraege_daten';

    protected $casts = [
        'id'        => 'integer',
        'antrag_id' => 'integer',
    ];

    public function antrag(): BelongsTo
    {
        return $this->belongsTo(Antrag::class, 'antrag_id', 'id');
    }
}
