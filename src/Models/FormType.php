<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_antrag_typen` — Antragstyp-Definitionen.
 *
 * Jeder Antragstyp definiert ein Formular über die zugehörigen
 * FormField-Records. Beim Stellen eines Antrags wird ein Antrag-Record
 * angelegt + ein FormData-Record pro Feld.
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FormField> $felder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Form>        $antraege
 *
 * @method static Builder<static> active()
 */
class FormType extends Model
{
    protected $table = 'intra_antrag_typen';

    /** @var array<string, string> */
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
     *
     * @return HasMany<FormField, $this>
     */
    public function felder(): HasMany
    {
        $felder = $this->hasMany(FormField::class, 'antragstyp_id', 'id');
        $felder->orderBy('sortierung');

        return $felder;
    }

    /**
     * Alle Anträge dieses Typs (am häufigsten via Form::with('typ') geladen).
     *
     * @return HasMany<Form, $this>
     */
    public function antraege(): HasMany
    {
        return $this->hasMany(Form::class, 'antragstyp_id', 'id');
    }

    /**
     * Convenience-Scope: nur aktive Antragstypen, sortiert für die Auswahl-View.
     *
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('aktiv', 1)
            ->orderBy('sortierung')
            ->orderBy('name');
    }
}
