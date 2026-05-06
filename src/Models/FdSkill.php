<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_mitarbeiter_fwquali` — Feuerwehr-Qualifikationen.
 *
 * Der `none`-Flag markiert den "Keine Qualifikation"-Eintrag, der für neue
 * Mitarbeiter standardmäßig gesetzt wird. Drei Namens-Varianten wie bei den
 * Dienstgraden.
 *
 * @property int    $id
 * @property int    $priority
 * @property string $shortname
 * @property string $name
 * @property string $name_m
 * @property string $name_w
 * @property bool   $none
 * @property \DateTime $created_at
 */
class FdSkill extends Model
{
    protected $table = 'intra_mitarbeiter_fwquali';

    protected $casts = [
        'id'         => 'integer',
        'priority'   => 'integer',
        'none'       => 'boolean',
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
     */
    public function scopeReal($query)
    {
        return $query->where('none', 0)->orderBy('priority');
    }
}
