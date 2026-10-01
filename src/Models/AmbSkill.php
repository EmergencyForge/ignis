<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-Model für `intra_mitarbeiter_rdquali` — Rettungsdienst-Qualifikationen.
 *
 * @property int    $id
 * @property int    $priority
 * @property string $name
 * @property string $name_m
 * @property string $name_w
 * @property string|null $abkuerzung
 * @property bool   $none
 * @property bool   $trainable
 * @property \DateTime $created_at
 *
 * @method static Builder<static> real()
 */
class AmbSkill extends Model
{
    protected $table = 'intra_mitarbeiter_rdquali';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'integer',
        'priority'   => 'integer',
        'none'       => 'boolean',
        'trainable'  => 'boolean',
        'created_at' => 'datetime',
    ];

    public function displayName(?int $geschlecht): string
    {
        return match ($geschlecht) {
            0 => $this->name_m ?: $this->name,
            1 => $this->name_w ?: $this->name,
            default => $this->name,
        };
    }

    /**
     * Echte Qualifikationen ohne den "Keine"-Eintrag, sortiert nach Priority.
     *
     * @param Builder<self> $query
     */
    public function scopeReal(Builder $query): void
    {
        $query->where('none', 0)->orderBy('priority');
    }
}
