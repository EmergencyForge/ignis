<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_antrag_felder` — Feld-Definitionen pro Antragstyp.
 *
 * Diese Records werden vom create.php Form-Generator gelesen, um das Formular
 * dynamisch aufzubauen, und vom view.php um die eingereichten Werte mit ihren
 * Labels auszugeben.
 *
 * @property int         $id
 * @property int         $antragstyp_id
 * @property string      $feldname     Technischer Name (POST-Key, FormData-Match)
 * @property string      $label        Anzeigetext im Formular
 * @property string      $feldtyp      enum: text|textarea|number|date|select|checkbox|email|time|tel
 * @property string|null $optionen     Newline-getrennt für select-Typ
 * @property bool        $pflichtfeld
 * @property string|null $platzhalter
 * @property int         $sortierung
 * @property string      $breite       enum: full|half
 * @property string|null $standardwert
 * @property string|null $hinweistext
 * @property bool        $readonly
 * @property string|null $auto_fill    fullname|dienstnr|dienstgrad|discordtag|fullname_dienstnr
 * @property-read FormType $typ
 */
class FormField extends Model
{
    protected $table = 'intra_antrag_felder';

    protected $casts = [
        'id'            => 'integer',
        'antragstyp_id' => 'integer',
        'pflichtfeld'   => 'boolean',
        'sortierung'    => 'integer',
        'readonly'      => 'boolean',
    ];

    public function typ(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'antragstyp_id', 'id');
    }

    /**
     * Bei `feldtyp = select` sind die Optionen Newline-separated im
     * `optionen`-Feld gespeichert. Diese Methode parst sie zu einem Array
     * (leere Zeilen werden gefiltert).
     *
     * @return array<int,string>
     */
    public function selectOptions(): array
    {
        if ($this->feldtyp !== 'select' || $this->optionen === null) {
            return [];
        }
        return array_values(array_filter(
            array_map('trim', explode("\n", $this->optionen)),
            fn($v) => $v !== ''
        ));
    }
}
