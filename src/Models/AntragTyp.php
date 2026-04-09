<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_antrag_typen` — Antragstyp-Definitionen.
 *
 * Jeder Antragstyp definiert ein Formular über die zugehörigen
 * AntragField-Records. Beim Stellen eines Antrags wird ein Antrag-Record
 * angelegt + ein AntragData-Record pro Feld.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $beschreibung
 * @property string      $icon
 * @property bool        $aktiv
 * @property int         $sortierung
 * @property string|null $tabelle_name        Legacy: Name der ursprünglichen Zieltabelle
 * @property \DateTime   $erstellt_am
 * @property int|null    $erstellt_von
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AntragField> $felder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Antrag>      $antraege
 */
class AntragTyp extends Model
{
    protected $table = 'intra_antrag_typen';

    protected $casts = [
        'id'           => 'integer',
        'aktiv'        => 'boolean',
        'sortierung'   => 'integer',
        'erstellt_von' => 'integer',
        'erstellt_am'  => 'datetime',
    ];

    /**
     * Felder-Definitionen für dieses Antragstyp-Formular,
     * sortiert nach Sortierungsfeld.
     */
    public function felder(): HasMany
    {
        return $this->hasMany(AntragField::class, 'antragstyp_id', 'id')
            ->orderBy('sortierung');
    }

    /**
     * Alle Anträge dieses Typs (am häufigsten via Antrag::with('typ') geladen).
     */
    public function antraege(): HasMany
    {
        return $this->hasMany(Antrag::class, 'antragstyp_id', 'id');
    }

    /**
     * Convenience-Scope: nur aktive Antragstypen, sortiert für die Auswahl-View.
     */
    public function scopeActive($query)
    {
        return $query->where('aktiv', 1)
            ->orderBy('sortierung')
            ->orderBy('name');
    }
}
